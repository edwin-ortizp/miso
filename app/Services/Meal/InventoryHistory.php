<?php

namespace App\Services\Meal;

use App\Models\InventoryProductSuccessor;
use App\Models\MealInventoryMovement;
use App\Models\MealInventoryOperation;
use App\Models\MealPlanEntry;
use App\Models\MealPreparation;
use App\Models\Purchase;
use App\Models\ShoppingItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Read-only ledger projections shared by Livewire and MCP. Every relation is owner scoped. */
class InventoryHistory
{
    public const TYPES = ['opening' => 'Apertura', 'purchase' => 'Compra', 'purchase_correction' => 'Corrección de compra',
        'consume' => 'Consumo', 'cook' => 'Cocción', 'reversal' => 'Reversión', 'count' => 'Conteo físico',
        'adjustment' => 'Ajuste', 'merge' => 'Fusión', 'reclassification' => 'Cambio de unidad'];

    public const CHANNELS = ['web' => 'Web', 'mcp' => 'MCP', 'system' => 'Sistema', 'import' => 'Importación', 'legacy' => 'Sin canal registrado'];

    public function documents(int $userId, array $filters = []): array
    {
        $filters = $this->filters($filters);
        if (! empty($filters['item_id'])) {
            $this->product($userId, $filters['item_id']);
        }
        $query = $this->operations($userId);
        $total = (clone $query)->count();
        $this->filterOperations($query, $userId, $filters);
        if (! empty($filters['item_id'])) {
            $id = $filters['item_id'];
            $query->whereHas('movements', fn (Builder $q) => $this->forProduct($q->withoutGlobalScopes()->where('user_id', $userId), $id));
        }
        if (! empty($filters['search'])) {
            $like = '%'.$filters['search'].'%';
            $query->where(fn (Builder $q) => $q->where('id', 'like', $like)->orWhere('reason', 'like', $like)
                ->orWhere('origin->reference', 'like', $like)
                ->orWhereHas('movements', fn (Builder $m) => $m->withoutGlobalScopes()->where('user_id', $userId)->where('name', 'like', $like)));
        }
        $page = $query->with(['movements' => fn ($q) => $q->withoutGlobalScopes()->where('user_id', $userId)->orderBy('created_at')->orderBy('id')])
            ->orderByDesc('created_at')->orderByDesc('id')->paginate($filters['per_page'], ['*'], 'page', $filters['page']);
        $links = $this->links($userId, $page->getCollection());

        return ['documents' => $page->getCollection()->map(fn ($op) => $this->header($op, $links))->all(),
            'pagination' => $this->pagination($page), 'total_documents' => $total];
    }

    public function document(int $userId, string $id, array $filters = []): array
    {
        $filters = $this->filters($filters);
        $operation = $this->operations($userId)->with(['movements' => fn ($q) => $q->withoutGlobalScopes()->where('user_id', $userId)->orderBy('created_at')->orderBy('id')])->findOrFail($id);
        $lines = $this->lines($operation);
        $page = new LengthAwarePaginator(array_values(array_slice($lines, ($filters['page'] - 1) * $filters['per_page'], $filters['per_page'])), count($lines), $filters['per_page'], $filters['page']);
        $visible = $page->getCollection();
        $productIds = $visible->pluck('shopping_item_id')->filter()->unique()->values();
        $owned = ShoppingItem::withoutGlobalScopes()->where('user_id', $userId)->whereIn('id', $productIds)->pluck('id')
            ->merge(MealInventoryMovement::withoutGlobalScopes()->where('user_id', $userId)->whereIn('historical_product_id', $productIds)->pluck('historical_product_id'))->unique()->all();
        $links = $this->links($userId, collect([$operation]));
        $corrections = $this->operations($userId)->where('corrects_operation_id', $id)->orderBy('created_at')->orderBy('id')->get(['id', 'document_type', 'created_at']);

        return ['document' => $this->header($operation, $links),
            'lines' => $visible->map(fn ($line) => $line + ['kardex_available' => in_array($line['shopping_item_id'], $owned, true)])->all(),
            'pagination' => $this->pagination($page),
            'corrections' => $corrections->map(fn ($op) => ['id' => $op->id, 'type' => $op->document_type, 'recorded_at' => $op->created_at->toIso8601String()])->all()];
    }

    public function movements(int $userId, string $productId, array $filters = []): array
    {
        $filters = $this->filters($filters);
        $product = $this->product($userId, $productId);
        $query = $this->forProduct(MealInventoryMovement::withoutGlobalScopes()->where('user_id', $userId), $productId);
        // Movement has no relation in the legacy model: use an owned operation subquery.
        $operations = $this->operations($userId);
        $this->filterOperations($operations, $userId, $filters);
        $query->whereIn('operation_id', $operations->select('id'));
        $total = $this->forProduct(MealInventoryMovement::withoutGlobalScopes()->where('user_id', $userId), $productId)->count();
        $opening = $this->forProduct(MealInventoryMovement::withoutGlobalScopes()->where('user_id', $userId), $productId)
            ->where('opening_marker', 'initial')->first();
        $page = $query->orderByRaw('CASE WHEN sequence IS NULL THEN 1 ELSE 0 END')->orderByDesc('sequence')->orderByDesc('created_at')->orderByDesc('id')
            ->paginate($filters['per_page'], ['*'], 'page', $filters['page']);
        $ops = $this->operations($userId)->whereIn('id', $page->getCollection()->pluck('operation_id'))->get()->keyBy('id');
        $links = $this->links($userId, $ops);
        $rows = $page->getCollection()->map(function ($movement) use ($ops, $links) {
            $op = $ops[$movement->operation_id];

            return [
                'id' => $movement->id, 'operation_id' => $op->id, 'sequence' => $movement->sequence,
                'segment_id' => $movement->segment_id, 'name' => $movement->name, 'unit' => $movement->unit,
                'delta' => $this->quantity($movement->getRawOriginal('delta')),
                'balance_before' => $movement->balance_before, 'balance_after' => $movement->balance_after,
                'legacy' => $movement->sequence === null, 'compensates_movement_id' => $movement->compensates_movement_id,
                'document' => $this->header($op, $links, counts: false),
            ];
        })->all();

        return ['product' => $product, 'coverage_started_at' => $opening?->created_at->toIso8601String(),
            'movements' => $rows, 'pagination' => $this->pagination($page), 'total_movements' => $total];
    }

    public function product(int $userId, string $id): array
    {
        $live = ShoppingItem::withoutGlobalScopes()->where('user_id', $userId)->find($id);
        $historical = $this->forProduct(MealInventoryMovement::withoutGlobalScopes()->where('user_id', $userId), $id)->orderByDesc('created_at')->orderByDesc('id')->first();
        abort_unless($live || $historical, 404);
        $successions = InventoryProductSuccessor::withoutGlobalScopes()->where('user_id', $userId)->get();
        $next = $successions->keyBy('source_product_id');
        $destination = $id;
        $seen = [];
        while (isset($next[$destination]) && ! isset($seen[$destination])) {
            $seen[$destination] = true;
            $destination = $next[$destination]->target_product_id;
        }
        $successor = $destination !== $id && ! isset($seen[$destination]) ? ShoppingItem::withoutGlobalScopes()->where('user_id', $userId)->find($destination) : null;
        $predecessors = [];
        $visited = [$id => true];
        $queue = [$id];
        while ($queue !== []) {
            $target = array_shift($queue);
            foreach ($successions->where('target_product_id', $target) as $link) {
                if (! isset($visited[$link->source_product_id])) {
                    $visited[$link->source_product_id] = true;
                    $queue[] = $link->source_product_id;
                    $predecessors[] = $link->source_product_id;
                }
            }
        }
        $names = ShoppingItem::withoutGlobalScopes()->where('user_id', $userId)->whereIn('id', $predecessors)->pluck('name', 'id');
        $oldNames = MealInventoryMovement::withoutGlobalScopes()->where('user_id', $userId)->whereIn('historical_product_id', $predecessors)
            ->orderBy('created_at')->orderBy('id')->get(['historical_product_id', 'name'])->pluck('name', 'historical_product_id');

        return ['id' => $id, 'name' => $live?->name ?? $historical->name, 'stock' => $live ? $this->quantity($live->getRawOriginal('stock')) : null,
            'unit' => $live?->base_unit ?? $historical?->unit, 'active' => $live !== null,
            'successor' => $successor ? ['id' => $successor->id, 'name' => $successor->name, 'stock' => $this->quantity($successor->getRawOriginal('stock')), 'unit' => $successor->base_unit] : null,
            'predecessors' => collect($predecessors)->filter(fn ($key) => isset($names[$key]) || isset($oldNames[$key]))->map(fn ($key) => ['id' => $key, 'name' => $names[$key] ?? $oldNames[$key]])->values()->all()];
    }

    public function filters(array $filters): array
    {
        $data = array_filter($filters, fn ($value) => $value !== '' && $value !== null);
        $data = Validator::make($data, [
            'from' => ['sometimes', 'date_format:Y-m-d'], 'to' => ['sometimes', 'date_format:Y-m-d', ...(! empty($data['from']) ? ['after_or_equal:from'] : [])],
            'type' => ['sometimes', Rule::in(array_keys(self::TYPES))], 'channel' => ['sometimes', Rule::in(array_keys(self::CHANNELS))],
            'item_id' => ['sometimes', 'uuid'], 'search' => ['sometimes', 'string', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ], ['to.after_or_equal' => 'Hasta debe ser igual o posterior a Desde.'])->validate();

        return $data + ['page' => 1, 'per_page' => 50];
    }

    public function returnUrl(int $userId, array $state): string
    {
        $filters = $this->safeQuery(is_array($state['filters'] ?? null) ? $state['filters'] : []);
        if (($state['screen'] ?? '') === 'ingredients') {
            return route('meals.ingredients', $filters);
        }
        if (($state['screen'] ?? '') === 'document' && is_string($state['id'] ?? null)
            && $this->operations($userId)->whereKey($state['id'])->exists()) {
            $parent = is_array($state['parent'] ?? null) ? $state['parent'] : [];
            $parent = ['screen' => ($parent['screen'] ?? '') === 'ingredients' ? 'ingredients' : 'documents',
                'filters' => $this->safeQuery(is_array($parent['filters'] ?? null) ? $parent['filters'] : [])];

            return route('meals.inventory.show', ['operation' => $state['id'], 'return' => $parent]);
        }

        return route('meals.inventory', $filters);
    }

    public function safeQuery(array $values): array
    {
        $result = [];
        foreach (['q', 'from', 'to', 'type', 'channel', 'itemId', 'page', 'per_page', 'ingredient', 'categories', 'locations', 'store', 'price', 'cart', 'data', 'usage'] as $key) {
            $value = $values[$key] ?? null;
            if (is_scalar($value) && mb_strlen((string) $value) <= 255) {
                $result[$key] = $value;
            } elseif (is_array($value) && in_array($key, ['categories', 'locations'], true)) {
                $result[$key] = array_values(array_filter(array_slice($value, 0, 100), fn ($v) => is_scalar($v) && mb_strlen((string) $v) <= 255));
            }
        }

        return $result;
    }

    private function operations(int $userId): Builder
    {
        return MealInventoryOperation::withoutGlobalScopes()->where('user_id', $userId)->where(fn ($q) => $q->whereNotNull('document_lines')
            ->orWhereHas('movements', fn ($m) => $m->withoutGlobalScopes()->where('user_id', $userId)));
    }

    private function forProduct(Builder $query, string $id): Builder
    {
        return $query->whereNull('preparation_id')->where(fn ($q) => $q->where('historical_product_id', $id)
            ->orWhere(fn ($old) => $old->whereNull('historical_product_id')->where('shopping_item_id', $id)));
    }

    private function filterOperations(Builder $query, int $userId, array $filters): void
    {
        foreach (['from' => '>=', 'to' => '<='] as $key => $operator) {
            if (! empty($filters[$key])) {
                $query->whereRaw('DATE(COALESCE(effective_at, effective_date, created_at)) '.$operator.' ?', [$filters[$key]]);
            }
        }
        if (! empty($filters['type'])) {
            $type = $filters['type'];
            $actions = $type === 'reversal' ? ['revert', 'cancel_preparation'] : [$type];
            $query->where(fn ($q) => $q->where('document_type', $type)->orWhere(fn ($old) => $old->whereNull('document_type')->whereIn('action', $actions)));
        }
        if (! empty($filters['channel'])) {
            $filters['channel'] === 'legacy' ? $query->whereNull('channel') : $query->where('channel', $filters['channel']);
        }
    }

    private function links(int $userId, Collection $operations): array
    {
        $links = [];
        foreach (['purchase' => Purchase::class, 'meal' => MealPlanEntry::class, 'preparation' => MealPreparation::class] as $type => $model) {
            $ids = $operations->filter(fn ($op) => ($op->origin['type'] ?? null) === $type)->map(fn ($op) => $op->origin['id'] ?? null)->filter()->unique();
            $links[$type] = $ids->isEmpty() ? [] : $model::withoutGlobalScopes()->where('user_id', $userId)->whereIn('id', $ids)->pluck('id')->map(fn ($id) => (string) $id)->all();
        }
        $ids = $operations->pluck('corrects_operation_id')->filter()->unique();
        $links['corrections'] = $ids->isEmpty() ? [] : $this->operations($userId)->whereIn('id', $ids)->pluck('id')->all();

        return $links;
    }

    private function header(MealInventoryOperation $operation, array $links, bool $counts = true): array
    {
        $type = $operation->document_type ?? (in_array($operation->action, ['revert', 'cancel_preparation'], true) ? 'reversal' : $operation->action);
        $origin = $operation->origin ?? [];
        $originId = (string) ($origin['id'] ?? '');
        $originType = $origin['type'] ?? '';
        $url = null;
        if ($originId !== '' && in_array($originId, $links[$originType] ?? [], true)) {
            $url = match ($originType) {
                'purchase' => route('meals.purchases.show', ['purchase' => $originId]),
                'meal' => route('meals.weekly', ['meal' => $originId]),
                'preparation' => route('meals.preparations'), default => null,
            };
        }
        $lines = $counts ? $this->lines($operation) : [];

        return ['id' => $operation->id, 'type' => $type, 'type_label' => self::TYPES[$type] ?? 'Movimiento anterior',
            'recorded_at' => $operation->created_at->toIso8601String(), 'effective_date' => $operation->effective_date?->toDateString(),
            'effective_at' => $operation->effective_at?->toIso8601String(), 'channel' => $operation->channel ?? 'legacy',
            'channel_label' => self::CHANNELS[$operation->channel ?? 'legacy'] ?? 'Sin canal registrado',
            'reason' => $operation->reason ?? 'Sin motivo registrado', 'origin' => $origin, 'origin_url' => $url,
            'origin_label' => match ($originType) {
                'purchase' => 'Compra', 'meal' => 'Comida', 'preparation' => 'Preparación', default => 'Registro de inventario'
            },
            'corrects_operation_id' => in_array($operation->corrects_operation_id, $links['corrections'] ?? [], true) ? $operation->corrects_operation_id : null,
            'legacy' => $operation->document_lines === null,
            'line_count' => $counts ? count($lines) : null,
            'product_count' => $counts ? collect($lines)->pluck('shopping_item_id')->filter()->unique()->count() : null,
            'movement_count' => $counts ? $operation->movements->count() : null];
    }

    private function lines(MealInventoryOperation $operation): array
    {
        $owned = $operation->movements->keyBy('id');
        $lines = $operation->document_lines;
        if ($lines === null) {
            $lines = $operation->movements->values()->map(fn ($m, $index) => ['position' => $index + 1, 'line_id' => $m->id,
                'shopping_item_id' => $m->historical_product_id ?? $m->shopping_item_id, 'preparation_id' => $m->preparation_id,
                'name' => $m->name, 'unit' => $m->unit, 'delta' => $m->getRawOriginal('delta'),
                'balance_before' => null, 'balance_after' => null, 'movement_ids' => [$m->id]])->all();
        }

        return collect($lines)->sortBy('position')->values()->map(function ($line, $index) use ($owned) {
            return ['position' => $line['position'] ?? $index + 1, 'line_id' => $line['line_id'],
                'shopping_item_id' => $line['shopping_item_id'] ?? null, 'preparation_id' => $line['preparation_id'] ?? null,
                'name' => $line['name'], 'variant_label' => $line['variant_label'] ?? null, 'unit' => $line['unit'],
                'delta' => $this->quantity($line['delta']), 'balance_before' => isset($line['balance_before']) ? $this->quantity($line['balance_before']) : null,
                'balance_after' => isset($line['balance_after']) ? $this->quantity($line['balance_after']) : null,
                'movement_ids' => array_values(array_filter($line['movement_ids'] ?? [], fn ($id) => isset($owned[$id]))),
                'compensates_movement_id' => $line['compensates_movement_id'] ?? null];
        })->all();
    }

    private function pagination(LengthAwarePaginator $page): array
    {
        return ['page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage(),
            'next_page' => $page->hasMorePages() ? $page->currentPage() + 1 : null];
    }

    private function quantity(int|float|string $number): string
    {
        return number_format((float) $number, 3, '.', '');
    }
}
