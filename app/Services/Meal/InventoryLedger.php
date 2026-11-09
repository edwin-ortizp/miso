<?php

namespace App\Services\Meal;

use App\Events\Meal\InventoryMovementRecorded;
use App\Models\InventoryProductSuccessor;
use App\Models\MealInventoryMovement;
use App\Models\MealInventoryOperation;
use App\Models\MealPlanEntry;
use App\Models\MealPreparation;
use App\Models\Purchase;
use App\Models\ShoppingItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Quantities are calculated as integer thousandths; all public writes lock the user first. */
class InventoryLedger
{
    private const MAX = 999999999999; // shopping_items.stock is decimal(12,3).

    private const TYPES = ['opening', 'purchase', 'purchase_correction', 'consume', 'cook', 'reversal', 'count', 'adjustment', 'merge', 'reclassification'];

    /** Lines: shopping_item_id, quantity, unit, mode (delta/count), optional line_id/variant_label/compensates_movement_id. */
    public function record(int $userId, string $type, array $lines, array $context = [], ?string $key = null): array
    {
        if ($type === 'opening') {
            $this->fail('Usa la inicialización para registrar una apertura sin alterar el stock.');
        }
        $lines = $this->normalizeLines($lines);

        return $this->execute($userId, $type, $lines, $context, $key,
            fn (MealInventoryOperation $operation, array $header) => $this->applyLines($operation, $lines, $header));
    }

    public function count(int $userId, string $productId, int|float|string $total, string $unit, array $context = [], ?string $key = null): array
    {
        return $this->record($userId, 'count', [['shopping_item_id' => $productId, 'quantity' => $total, 'unit' => $unit, 'mode' => 'count']],
            $context + ['reason' => 'Conteo físico'], $key);
    }

    public function adjust(int $userId, string $productId, int|float|string $delta, string $unit, array $context = [], ?string $key = null): array
    {
        return $this->record($userId, 'adjustment', [['shopping_item_id' => $productId, 'quantity' => $delta, 'unit' => $unit]], $context, $key);
    }

    /** Catalog adapters supply validated attributes; stock/unit writes remain in this boundary. */
    public function saveProduct(int $userId, array $attributes, ?string $productId = null, ?string $key = null, array $context = []): ShoppingItem
    {
        $context += ['channel' => request()->is('mcp*', 'miso', 'life-tracker') ? 'mcp' : (app()->runningInConsole() ? 'system' : 'web'), 'reason' => $productId ? 'Conteo físico' : 'Saldo de apertura'];
        if (array_diff(array_keys($attributes), (new ShoppingItem)->getFillable())) {
            $this->fail('Los atributos del ingrediente no son válidos.');
        }
        if (array_key_exists('stock', $attributes)) {
            if (! is_numeric($attributes['stock']) || $attributes['stock'] < 0) {
                $this->fail('El stock debe ser una cantidad no negativa.');
            }
            $attributes['stock'] = self::decimal($this->thousandths($attributes['stock']));
        }
        if (isset($attributes['base_unit']) && ! array_key_exists($attributes['base_unit'], UnitConverter::BASE_UNITS)) {
            $this->fail('La unidad base no es válida.');
        }
        $result = $this->execute($userId, $productId ? 'count' : 'opening', ['product_id' => $productId, 'attributes' => $attributes, 'request' => $context['request'] ?? null], $context, $key,
            function (MealInventoryOperation $operation, array $header) use ($userId, $attributes, $productId) {
                if ($productId === null) {
                    $name = CatalogNames::normalize((string) ($attributes['name'] ?? ''));
                    if ($name === '' || ShoppingItem::withoutGlobalScopes()->where('user_id', $userId)->get(['name'])
                        ->contains(fn ($item) => CatalogNames::normalize($item->name) === $name)) {
                        $this->fail('El nombre del ingrediente está vacío o ya existe en tu catálogo.');
                    }
                    $product = new ShoppingItem($attributes + ['stock' => '0.000']);
                    $product->user_id = $userId;
                    $product->save();
                    if ($product->base_unit !== null) {
                        $this->opening($userId, $product, $header);
                    } elseif ($this->thousandths($product->getRawOriginal('stock')) !== 0) {
                        $this->fail('Define una unidad base antes de registrar existencias.');
                    }
                } else {
                    $product = ShoppingItem::withoutGlobalScopes()->where('user_id', $userId)->lockForUpdate()->find($productId)
                        ?? $this->fail('El ingrediente no está disponible o no te pertenece.');
                    $unit = $attributes['base_unit'] ?? $product->base_unit;
                    $stock = $attributes['stock'] ?? $product->getRawOriginal('stock');
                    if ($product->base_unit !== null && $unit !== $product->base_unit) {
                        $this->reclassifyProduct($operation, $product, $unit, $this->thousandths($stock), $header);
                    } else {
                        if ($product->base_unit === null && $unit !== null) {
                            $product->update(['base_unit' => $unit]);
                            $this->opening($userId, $product, $header);
                        }
                        if (array_key_exists('stock', $attributes)) {
                            $this->applyLines($operation, $this->normalizeLines([['shopping_item_id' => $product->id, 'unit' => $unit, 'mode' => 'count', 'quantity' => $stock]]), $header);
                        }
                    }
                    unset($attributes['stock'], $attributes['base_unit']);
                    if ($attributes) {
                        $product->update($attributes);
                    }
                }

                return ['shopping_item_id' => $product->id, 'operation_id' => $operation->id];
            });

        return $this->resolveProduct($userId, $result['shopping_item_id']);
    }

    public function resolveProduct(int $userId, string $id, ?string $unit = null): ShoppingItem
    {
        $id = $this->successor($userId, $id, $unit);
        $product = ShoppingItem::withoutGlobalScopes()->where('user_id', $userId)->find($id)
            ?? $this->fail('El ingrediente no está disponible o no te pertenece.');
        if ($unit !== null && $product->base_unit !== $unit) {
            $this->fail('La unidad base cambió; resuelve la incompatibilidad antes de operar.');
        }

        return $product;
    }

    private function reclassifyProduct(MealInventoryOperation $operation, ShoppingItem $product, string $unit, int $stock, array $header): void
    {
        $previous = $this->opening((int) $operation->user_id, $product);
        $before = $this->validateOpeningState($product, $previous);
        $header['document_type'] = 'reclassification';
        $operation->fill($header);
        $out = $this->movement($operation, $product, $before, 0, $previous->sequence + 1, $previous->segment_id, 'reclassification');
        $product->update(['base_unit' => $unit, 'stock' => self::decimal($stock)]);
        $segment = (string) Str::uuid();
        $in = $this->movement($operation, $product, 0, $stock, $previous->sequence + 2, $segment, 'reclassification', $segment);
        $operation->fill(['document_lines' => [
            $this->documentLine(0, ['line_id' => 'out', 'variant_label' => null, 'mode' => 'reclassification'], $out),
            $this->documentLine(1, ['line_id' => 'in', 'variant_label' => null, 'mode' => 'reclassification'], $in),
        ], 'document_fingerprint' => $this->fingerprint([$product->id, $unit, self::decimal($stock), $header])])->save();
    }

    /** Attach to a business operation already created inside the caller's transaction, avoiding nested documents. */
    public function apply(MealInventoryOperation $operation, string $type, array $lines, array $context = []): array
    {
        $lines = $this->normalizeLines($lines);
        $header = $this->header($type, $context);

        return DB::transaction(function () use ($operation, $lines, $header) {
            $this->lockUser((int) $operation->user_id);
            $stored = MealInventoryOperation::withoutGlobalScopes()->where('user_id', $operation->user_id)->lockForUpdate()->find($operation->id)
                ?? $this->fail('No se encontró la operación de inventario.');

            return $this->applyLines($stored, $lines, $header);
        }, 3);
    }

    /** Idempotent opening primitive; the preview/application command is a separate adapter. */
    public function initialize(int $userId, array $productIds, array $context = []): array
    {
        Validator::make(['ids' => $productIds], ['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['required', 'uuid']])->validate();

        return DB::transaction(function () use ($userId, $productIds, $context) {
            $this->lockUser($userId);
            $products = $this->products($userId, $productIds);
            $ids = [];
            foreach ($products as $product) {
                $this->opening($userId, $product, $context);
                $ids[] = MealInventoryMovement::withoutGlobalScopes()->where('user_id', $userId)->where('historical_product_id', $product->id)
                    ->where('opening_marker', 'initial')->firstOrFail()->operation_id;
            }

            return ['operation_ids' => $ids];
        }, 3);
    }

    /** Read-only inspection: never creates operations, movements or stock adjustments. */
    public function previewInitialization(int $userId, array $productIds): array
    {
        Validator::make(['ids' => $productIds], ['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['required', 'uuid']])->validate();
        if (! User::withoutGlobalScopes()->whereKey($userId)->exists()) {
            $this->fail('No se encontró el usuario de inventario.');
        }
        $rows = [];
        foreach ($this->products($userId, $productIds, lock: false) as $product) {
            $latest = MealInventoryMovement::withoutGlobalScopes()->where('user_id', $userId)
                ->where('historical_product_id', $product->id)->whereNotNull('sequence')->orderByDesc('sequence')->first();
            $opening = MealInventoryMovement::withoutGlobalScopes()->where('user_id', $userId)
                ->where('historical_product_id', $product->id)->where('opening_marker', 'initial')->first();
            $row = [
                'user_id' => $userId, 'shopping_item_id' => $product->id, 'name' => $product->name,
                'unit' => $product->base_unit, 'stock' => (string) $product->getRawOriginal('stock'),
                'status' => $latest ? 'initialized' : 'pending', 'message' => '', 'operation_id' => $opening?->operation_id,
            ];
            try {
                $row['stock'] = self::decimal($this->validateOpeningState($product, $latest));
                if (($latest !== null) !== ($opening !== null)) {
                    $this->fail('El libro no tiene una apertura inicial coherente; revisa el historial.');
                }
            } catch (ValidationException $exception) {
                $row['status'] = 'blocked';
                $row['message'] = implode(' ', $exception->validator->errors()->all());
            }
            $rows[] = $row;
        }

        return $rows;
    }

    public function compensate(int $userId, string $originalId, array $context = [], ?string $key = null): array
    {
        $context['corrects_operation_id'] = $originalId;

        return $this->execute($userId, 'reversal', ['original_id' => $originalId], $context, $key,
            function (MealInventoryOperation $operation, array $header) use ($userId, $originalId) {
                $original = MealInventoryOperation::withoutGlobalScopes()->where('user_id', $userId)->lockForUpdate()->find($originalId)
                    ?? $this->fail('No se encontró el documento original.');
                if ($original->reversed || $original->document_type === 'opening') {
                    $this->fail('El documento ya fue revertido o es una apertura.');
                }
                $movements = MealInventoryMovement::withoutGlobalScopes()->where('user_id', $userId)->where('operation_id', $originalId)
                    ->orderByDesc('sequence')->orderByDesc('created_at')->orderByDesc('id')->get();
                if ($movements->isEmpty() || $movements->contains(fn ($movement) => $movement->preparation_id !== null)) {
                    $this->fail('La reversión requiere movimientos de ingredientes; usa el flujo de comidas para preparaciones.');
                }
                $lines = $movements->map(fn ($movement) => [
                    'shopping_item_id' => $movement->historical_product_id ?? $movement->shopping_item_id,
                    'quantity' => self::decimal(-$this->thousandths($movement->getRawOriginal('delta'))),
                    'unit' => $movement->unit, 'compensates_movement_id' => $movement->id,
                ])->all();
                $result = $this->applyLines($operation, $this->normalizeLines($lines), $header);
                $original->update(['reversed' => true]);

                return $result;
            });
    }

    /** Transfers stock; the merge adapter remains responsible for catalog relations and deletion. */
    public function transfer(int $userId, string $sourceId, string $targetId, array $context = [], ?string $key = null): array
    {
        return $this->execute($userId, 'merge', ['source' => $sourceId, 'target' => $targetId], $context, $key,
            function (MealInventoryOperation $operation, array $header) use ($userId, $sourceId, $targetId) {
                if (InventoryProductSuccessor::withoutGlobalScopes()->where('user_id', $userId)->where('source_product_id', $sourceId)->exists()) {
                    $this->fail('El ingrediente origen ya fue combinado.');
                }
                $targetId = $this->successor($userId, $targetId);
                if ($sourceId === $targetId) {
                    $this->fail('Elige productos distintos; una fusión no puede formar ciclos.');
                }
                $products = $this->products($userId, [$sourceId, $targetId]);
                $source = $products[$sourceId];
                $target = $products[$targetId];
                if ($source->base_unit !== $target->base_unit) {
                    $this->fail('Los ingredientes deben tener la misma unidad base.');
                }
                $quantity = $this->thousandths($source->getRawOriginal('stock'));
                $lines = $this->normalizeLines([
                    ['shopping_item_id' => $sourceId, 'quantity' => self::decimal(-$quantity), 'unit' => $source->base_unit],
                    ['shopping_item_id' => $targetId, 'quantity' => self::decimal($quantity), 'unit' => $target->base_unit],
                ]);
                $result = $this->applyLines($operation, $lines, $header);
                InventoryProductSuccessor::create([
                    'user_id' => $userId, 'source_product_id' => $sourceId, 'target_product_id' => $targetId,
                    'unit' => $source->base_unit, 'operation_id' => $operation->id,
                ]);

                return $result;
            });
    }

    private function execute(int $userId, string $type, array $input, array $context, ?string $key, callable $work): array
    {
        $header = $this->header($type, $context);
        $key ??= (string) Str::uuid();
        Validator::make(['key' => $key], ['key' => ['required', 'string', 'max:100']])->validate();
        $fingerprint = $this->fingerprint([$type, $input, $header]);

        return DB::transaction(function () use ($userId, $type, $header, $key, $fingerprint, $work) {
            $this->lockUser($userId);
            $existing = MealInventoryOperation::withoutGlobalScopes()->where('user_id', $userId)->where('operation_key', $key)->first();
            if ($existing) {
                if ($existing->fingerprint !== $fingerprint || $existing->result === null) {
                    $this->fail('La clave de operación ya se utilizó con otros datos o está incompleta.');
                }

                return $existing->result;
            }
            $this->validateReferences($userId, $header);
            $operation = MealInventoryOperation::create([
                'user_id' => $userId, 'operation_key' => $key, 'action' => 'inventory:'.$type, 'fingerprint' => $fingerprint,
            ]);
            $result = $work($operation, $header);
            $operation->refresh()->update(['result' => $result]);

            return $result;
        }, 3);
    }

    private function applyLines(MealInventoryOperation $operation, array $lines, array $header): array
    {
        $fingerprint = $this->fingerprint([$lines, $header]);
        if ($operation->document_lines !== null) {
            if ($operation->document_fingerprint !== $fingerprint) {
                $this->fail('La operación ya tiene un documento con otros datos.');
            }

            return $this->result($operation);
        }
        $userId = (int) $operation->user_id;
        $this->validateReferences($userId, $header);
        $operation->fill($header);
        $resolved = [];
        foreach ($lines as $index => $line) {
            if ($line['shopping_item_id'] !== null) {
                $resolved[$index] = $this->successor($userId, $line['shopping_item_id'], $line['unit']);
            }
        }
        $products = $this->products($userId, $resolved);
        $preparationIds = collect($lines)->pluck('preparation_id')->filter()->unique()->sort()->values();
        $preparations = MealPreparation::withoutGlobalScopes()->where('user_id', $userId)->whereIn('id', $preparationIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        if ($preparations->count() !== $preparationIds->count()) {
            $this->fail('Preparación ajena o inexistente.');
        }
        $latest = [];
        foreach ($products as $id => $product) {
            $latest[$id] = $this->opening($userId, $product);
        }
        $documentLines = [];
        foreach ($lines as $index => $line) {
            if ($line['preparation_id'] !== null) {
                $prep = $preparations[$line['preparation_id']];
                $before = $this->thousandths($prep->portions) + $this->thousandths((string) MealInventoryMovement::withoutGlobalScopes()->where('user_id', $userId)->where('preparation_id', $prep->id)->sum('delta'));
                $after = $before + $this->thousandths($line['quantity']);
                if ($prep->cancelled || $after < 0 || $after > $this->thousandths($prep->portions)) {
                    $this->fail("No hay porciones suficientes o la devolución supera lo producido: {$prep->name}.");
                }
                if ($line['compensates_movement_id'] && ! MealInventoryMovement::withoutGlobalScopes()->where('user_id', $userId)->where('preparation_id', $prep->id)->whereKey($line['compensates_movement_id'])->exists()) {
                    $this->fail('El movimiento original no corresponde a la preparación.');
                }
                $movement = MealInventoryMovement::create([
                    'user_id' => $userId, 'operation_id' => $operation->id, 'preparation_id' => $prep->id,
                    'name' => $prep->name, 'unit' => 'portion', 'delta' => self::decimal($after - $before),
                    'balance_before' => self::decimal($before), 'balance_after' => self::decimal($after),
                    'movement_type' => $header['document_type'], 'compensates_movement_id' => $line['compensates_movement_id'],
                ]);
                event(new InventoryMovementRecorded($userId, $operation->id, $movement->id, $header['origin']));
                $documentLines[] = $this->documentLine($index, $line, $movement);

                continue;
            }
            $product = $products[$resolved[$index]];
            if ($product->base_unit !== $line['unit']) {
                $this->fail('La unidad base del ingrediente cambió; resuelve la incompatibilidad.');
            }
            $before = $this->thousandths($product->getRawOriginal('stock'));
            $quantity = $this->thousandths($line['quantity']);
            $after = $line['mode'] === 'count' ? $quantity : $before + $quantity;
            if ($after < 0 || $after > self::MAX) {
                $this->fail('La operación dejaría stock negativo o superaría el máximo permitido.');
            }
            $compensation = $line['compensates_movement_id'];
            if ($compensation !== null) {
                $original = MealInventoryMovement::withoutGlobalScopes()->where('user_id', $userId)->find($compensation)
                    ?? $this->fail('El movimiento original no está disponible.');
                $originalProduct = $original->historical_product_id ?? $original->shopping_item_id;
                if (! $originalProduct || $this->successor($userId, $originalProduct, $original->unit) !== $product->id || $original->unit !== $line['unit']) {
                    $this->fail('El movimiento original no corresponde al ingrediente o unidad.');
                }
            }
            $previous = $latest[$product->id];
            if ($this->thousandths($previous->getRawOriginal('balance_after')) !== $before) {
                $this->fail('El stock cambió fuera del motor; concilia el inventario antes de continuar.');
            }
            $movement = $this->movement($operation, $product, $before, $after, $previous->sequence + 1, $previous->segment_id,
                $header['document_type'], null, $compensation);
            $latest[$product->id] = $movement;
            $product->update(['stock' => self::decimal($after)]);
            $documentLines[] = $this->documentLine($index, $line, $movement);
        }
        $operation->fill($header + ['document_lines' => $documentLines, 'document_fingerprint' => $fingerprint])->save();

        return $this->result($operation);
    }

    private function opening(int $userId, ShoppingItem $product, array $context = []): MealInventoryMovement
    {
        $latest = MealInventoryMovement::withoutGlobalScopes()->where('user_id', $userId)
            ->where('historical_product_id', $product->id)->whereNotNull('sequence')->orderByDesc('sequence')->first();
        $balance = $this->validateOpeningState($product, $latest);
        if ($latest) {
            return $latest;
        }
        $header = $this->header('opening', $context + ['channel' => 'system', 'reason' => 'Saldo de apertura']);
        $this->execute($userId, 'opening', ['product_id' => $product->id], $header, 'inventory-opening:'.$product->id,
            function (MealInventoryOperation $operation, array $header) use ($product, $balance) {
                $operation->fill($header);
                $movement = $this->movement($operation, $product, 0, $balance, 1, (string) Str::uuid(), 'opening', 'initial');
                $line = ['line_id' => '1', 'variant_label' => null];
                $operation->fill($header + [
                    'document_lines' => [$this->documentLine(0, $line, $movement)],
                    'document_fingerprint' => $this->fingerprint([$product->id, self::decimal($balance)]),
                ])->save();

                return $this->result($operation);
            });

        return MealInventoryMovement::withoutGlobalScopes()->where('user_id', $userId)->where('historical_product_id', $product->id)
            ->where('opening_marker', 'initial')->firstOrFail();
    }

    private function movement(MealInventoryOperation $operation, ShoppingItem $product, int $before, int $after, int $sequence,
        string $segment, string $type, ?string $openingMarker = null, ?string $compensation = null): MealInventoryMovement
    {
        $movement = MealInventoryMovement::create([
            'user_id' => $operation->user_id, 'operation_id' => $operation->id,
            'shopping_item_id' => $product->id, 'historical_product_id' => $product->id,
            'name' => $product->name, 'unit' => $product->base_unit, 'delta' => self::decimal($after - $before),
            'balance_before' => self::decimal($before), 'balance_after' => self::decimal($after),
            'sequence' => $sequence, 'segment_id' => $segment, 'opening_marker' => $openingMarker,
            'movement_type' => $type, 'compensates_movement_id' => $compensation,
        ]);
        event(new InventoryMovementRecorded((int) $operation->user_id, $operation->id, $movement->id, $operation->origin ?? []));

        return $movement;
    }

    private function documentLine(int $index, array $line, MealInventoryMovement $movement): array
    {
        return [
            'line_id' => $line['line_id'], 'position' => $index + 1,
            'shopping_item_id' => $movement->historical_product_id, 'name' => $movement->name,
            'preparation_id' => $movement->preparation_id,
            'variant_label' => $line['variant_label'], 'unit' => $movement->unit,
            'quantity' => $line['quantity'] ?? self::decimal($this->thousandths($movement->getRawOriginal('delta'))),
            'mode' => $line['mode'] ?? 'opening',
            'delta' => self::decimal($this->thousandths($movement->getRawOriginal('delta'))),
            'balance_before' => $movement->balance_before, 'balance_after' => $movement->balance_after,
            'movement_ids' => [$movement->id], 'compensates_movement_id' => $movement->compensates_movement_id,
        ];
    }

    private function result(MealInventoryOperation $operation): array
    {
        return ['operation_id' => $operation->id, 'document_lines' => $operation->document_lines,
            'movement_ids' => array_merge(...array_column($operation->document_lines ?? [], 'movement_ids'))];
    }

    private function validateOpeningState(ShoppingItem $product, ?MealInventoryMovement $latest): int
    {
        if (! array_key_exists($product->base_unit ?? '', UnitConverter::BASE_UNITS)) {
            $this->fail('El ingrediente necesita una unidad base válida antes de iniciar su inventario.');
        }
        $balance = $this->thousandths($product->getRawOriginal('stock'));
        if ($balance < 0) {
            $this->fail('No se puede abrir inventario con stock negativo.');
        }
        if ($latest && $latest->unit !== $product->base_unit) {
            $this->fail('La unidad base cambió; registra una reclasificación antes de operar.');
        }
        if ($latest && $this->thousandths($latest->getRawOriginal('balance_after')) !== $balance) {
            $this->fail('El stock cambió fuera del motor; concilia el inventario antes de continuar.');
        }

        return $balance;
    }

    private function products(int $userId, array $ids, bool $lock = true): array
    {
        $ids = array_values(array_unique($ids));
        sort($ids, SORT_STRING);
        $query = ShoppingItem::withoutGlobalScopes()->where('user_id', $userId)->whereIn('id', $ids)->orderBy('id');
        if ($lock) {
            $query->lockForUpdate();
        }
        $products = $query->get()->keyBy('id')->all();
        if (count($products) !== count($ids)) {
            $this->fail('Un ingrediente no está disponible o no te pertenece.');
        }

        return $products;
    }

    private function successor(int $userId, string $id, ?string $unit = null): string
    {
        $seen = [];
        while ($link = InventoryProductSuccessor::withoutGlobalScopes()->where('user_id', $userId)->where('source_product_id', $id)->first()) {
            if (isset($seen[$id]) || ($unit !== null && $link->unit !== $unit)) {
                $this->fail('La sucesión de ingredientes tiene un ciclo o unidad incompatible.');
            }
            $seen[$id] = true;
            $id = $link->target_product_id;
        }

        return $id;
    }

    private function lockUser(int $userId): void
    {
        if (! User::withoutGlobalScopes()->whereKey($userId)->lockForUpdate()->first(['id'])) {
            $this->fail('No se encontró el usuario de inventario.');
        }
    }

    private function header(string $type, array $context): array
    {
        $header = [
            'document_type' => $type, 'channel' => $context['channel'] ?? 'system', 'reason' => $context['reason'] ?? $type,
            'origin' => $context['origin'] ?? [], 'effective_date' => $context['effective_date'] ?? null,
            'effective_at' => $context['effective_at'] ?? null, 'corrects_operation_id' => $context['corrects_operation_id'] ?? null,
        ];
        Validator::make($header, [
            'document_type' => ['required', Rule::in(self::TYPES)], 'channel' => ['required', Rule::in(['web', 'mcp', 'system', 'import'])],
            'reason' => ['required', 'string', 'max:1000'], 'origin' => ['array:type,id,reference,revision'],
            'origin.type' => ['sometimes', Rule::in(['manual', 'import', 'purchase', 'meal', 'preparation'])],
            'origin.id' => ['sometimes', 'nullable', 'string', 'max:100'],
            'origin.reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'origin.revision' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'effective_date' => ['nullable', 'date_format:Y-m-d', 'prohibits:effective_at'],
            'effective_at' => ['nullable', 'date', 'prohibits:effective_date'], 'corrects_operation_id' => ['nullable', 'uuid'],
        ])->validate();

        return $header;
    }

    private function validateReferences(int $userId, array $header): void
    {
        $id = $header['origin']['id'] ?? null;
        if ($id !== null) {
            $model = match ($header['origin']['type'] ?? null) {
                'purchase' => Purchase::class, 'meal' => MealPlanEntry::class, 'preparation' => MealPreparation::class,
                default => null,
            };
            if (! $model || ! $model::withoutGlobalScopes()->where('user_id', $userId)->whereKey($id)->exists()) {
                $this->fail('El documento origen no está disponible o no te pertenece.');
            }
        }
        if ($header['corrects_operation_id'] !== null
            && ! MealInventoryOperation::withoutGlobalScopes()->where('user_id', $userId)->whereKey($header['corrects_operation_id'])->exists()) {
            $this->fail('El documento original no está disponible o no te pertenece.');
        }
    }

    private function normalizeLines(array $lines): array
    {
        Validator::make(['lines' => $lines], [
            'lines' => ['required', 'array', 'min:1', 'max:1000'],
            'lines.*.shopping_item_id' => ['nullable', 'required_without:lines.*.preparation_id', 'uuid'],
            'lines.*.preparation_id' => ['nullable', 'required_without:lines.*.shopping_item_id', 'uuid'],
            'lines.*.quantity' => ['required', 'numeric'],
            'lines.*.unit' => ['required', Rule::in([...array_keys(UnitConverter::BASE_UNITS), 'portion'])],
            'lines.*.mode' => ['sometimes', Rule::in(['delta', 'count'])],
            'lines.*.line_id' => ['sometimes', 'string', 'max:100', 'distinct'],
            'lines.*.variant_label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'lines.*.compensates_movement_id' => ['sometimes', 'nullable', 'uuid'],
        ])->validate();
        $normalized = [];
        foreach (array_values($lines) as $index => $line) {
            $mode = $line['mode'] ?? 'delta';
            $preparationId = $line['preparation_id'] ?? null;
            $productId = $line['shopping_item_id'] ?? null;
            if (($preparationId !== null && ($productId !== null || $line['unit'] !== 'portion' || $mode !== 'delta'))
                || ($preparationId === null && $line['unit'] === 'portion')) {
                $this->fail('Elige ingrediente o preparación con su unidad correspondiente.');
            }
            if ($mode === 'count' && $line['quantity'] < 0) {
                $this->fail('El total contado no puede ser negativo.');
            }
            $normalized[] = [
                'shopping_item_id' => $productId, 'preparation_id' => $preparationId, 'unit' => $line['unit'], 'mode' => $mode,
                'quantity' => self::decimal($this->thousandths($line['quantity'])), 'line_id' => $line['line_id'] ?? (string) ($index + 1),
                'variant_label' => $line['variant_label'] ?? null, 'compensates_movement_id' => $line['compensates_movement_id'] ?? null,
            ];
        }
        if (count(array_unique(array_column($normalized, 'line_id'))) !== count($normalized)) {
            $this->fail('Los identificadores de línea deben ser distintos.');
        }

        return $normalized;
    }

    private function thousandths(int|float|string $quantity): int
    {
        if (is_float($quantity) && ! is_finite($quantity)) {
            $this->fail('La cantidad debe ser un número finito.');
        }
        $text = is_float($quantity) ? json_encode($quantity, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION) : trim((string) $quantity);
        if (! preg_match('/^([+-]?)(\d*)(?:\.(\d*))?(?:[eE]([+-]?\d+))?$/D', $text, $parts)
            || (($parts[2] ?? '') === '' && ($parts[3] ?? '') === '') || abs((int) ($parts[4] ?? 0)) > 20) {
            $this->fail('La cantidad no es válida o supera el máximo permitido.');
        }
        $whole = $parts[2] ?: '0';
        $digits = $whole.($parts[3] ?? '');
        $point = strlen($whole) + (int) ($parts[4] ?? 0) + 3;
        $digits = str_repeat('0', max(0, -$point)).$digits;
        $point = max(0, $point);
        $digits = str_pad($digits, $point + 1, '0');
        $integer = ltrim(substr($digits, 0, $point), '0') ?: '0';
        if (strlen($integer) > 12) {
            $this->fail('La cantidad supera el máximo permitido.');
        }
        $value = (int) $integer + ((int) $digits[$point] >= 5 ? 1 : 0);
        if ($value > self::MAX) {
            $this->fail('La cantidad supera el máximo permitido.');
        }

        return ($parts[1] === '-' ? -1 : 1) * $value;
    }

    private static function decimal(int $value): string
    {
        return ($value < 0 ? '-' : '').intdiv(abs($value), 1000).'.'.str_pad((string) (abs($value) % 1000), 3, '0', STR_PAD_LEFT);
    }

    private function fingerprint(array $input): string
    {
        $canonical = function (array $values) use (&$canonical): array {
            if (! array_is_list($values)) {
                ksort($values);
            }
            foreach ($values as &$value) {
                if (is_array($value)) {
                    $value = $canonical($value);
                }
            }

            return $values;
        };

        return hash('sha256', json_encode($canonical($input), JSON_THROW_ON_ERROR));
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['inventory' => $message]);
    }
}
