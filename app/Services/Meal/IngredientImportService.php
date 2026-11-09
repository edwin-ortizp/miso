<?php

namespace App\Services\Meal;

use App\Models\MealCategory;
use App\Models\ShoppingItem;
use App\Models\ShoppingItemAlias;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IngredientImportService
{
    public function normalize(string $value): string
    {
        $value = Str::ascii(Str::lower(trim($value)));
        $value = preg_replace('/^[\p{P}\p{S}\s]+|[\p{P}\p{S}\s]+$/u', '', $value) ?? '';

        return preg_replace('/\s+/u', ' ', $value) ?? '';
    }

    public function parse(string $input): array
    {
        $rows = [];
        $parts = preg_split('/[\r\n,;]+/u', $input, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($parts as $part) {
            $term = trim($part);
            $term = preg_replace('/^\s*(?:[-*•·]+|\d+[.)])\s*/u', '', $term) ?? '';
            $term = preg_replace(
                '/^(?:bueno[\s,]+)?(?:(?:necesito|quiero|debo|hay\s+que)\s+)?(?:comprar|agregar|añadir)\s+(?:(?:unos?|unas?|el|la|los|las)\s+)?/iu',
                '',
                $term
            ) ?? '';
            $term = trim($term);

            if ($term === '' || preg_match('/^(?:bueno|pues|entonces)$/iu', $term)) {
                continue;
            }

            $quantity = null;
            if (preg_match('/^(\d+)\s*(?:x\s*)?(.+)$/u', $term, $matches)) {
                $quantity = (int) $matches[1];
                $term = trim($matches[2]);
            }

            $normalized = $this->normalize($term);
            if ($normalized === '') {
                continue;
            }

            if (! isset($rows[$normalized])) {
                $rows[$normalized] = [
                    'source' => trim($part),
                    'term' => $term,
                    'normalized' => $normalized,
                    'quantity' => $quantity,
                ];

                continue;
            }

            if ($quantity !== null) {
                $rows[$normalized]['quantity'] = ($rows[$normalized]['quantity'] ?? 0) + $quantity;
            }
        }

        return array_values($rows);
    }

    public function resolve(array $parsedRows): array
    {
        $items = ShoppingItem::query()->with('aliases')->orderBy('name')->get();

        return array_map(function (array $row) use ($items) {
            $nameMatches = $items->filter(fn (ShoppingItem $item) => $this->normalize($item->name) === $row['normalized']);
            $aliasMatches = $items->filter(fn (ShoppingItem $item) => $item->aliases->contains(
                fn (ShoppingItemAlias $alias) => $alias->normalized_alias === $row['normalized']
            ));
            $matches = $nameMatches->merge($aliasMatches)->unique('id')->values();

            $row += [
                'item_id' => null,
                'item_name' => null,
                'action' => '',
                'link_item_id' => '',
                'new_name' => Str::ucfirst($row['term']),
                'category_id' => '',
                'unit' => '',
            ];

            if ($matches->count() > 1) {
                $row['status'] = 'conflict';
                $row['candidates'] = $matches->map(fn (ShoppingItem $item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                ])->all();

                return $row;
            }

            if ($matches->count() === 1) {
                $item = $matches->first();
                $row['status'] = $nameMatches->contains('id', $item->id) ? 'matched_name' : 'matched_alias';
                $row['item_id'] = $item->id;
                $row['item_name'] = $item->name;
                $row['action'] = 'match';

                return $row;
            }

            $row['status'] = 'unresolved';

            return $row;
        }, $parsedRows);
    }

    public function apply(array $rows, string $context): array
    {
        if (! in_array($context, ['shopping', 'ingredients'], true)) {
            throw ValidationException::withMessages(['context' => 'El contexto de importación no es válido.']);
        }

        return DB::transaction(function () use ($rows, $context) {
            $summary = ['found' => 0, 'created' => 0, 'linked' => 0, 'ignored' => 0, 'conflicts' => 0];

            foreach ($rows as $index => $row) {
                $action = $row['action'] ?? '';

                if ($action === 'ignore') {
                    if (($row['status'] ?? '') === 'conflict') {
                        $summary['conflicts']++;
                    } else {
                        $summary['ignored']++;
                    }

                    continue;
                }

                if ($action === 'match') {
                    $item = ShoppingItem::find($row['item_id'] ?? null);
                    if (! $item) {
                        throw ValidationException::withMessages(["rows.$index.item_id" => 'El ingrediente encontrado ya no está disponible.']);
                    }
                    $this->applyShoppingState($item, $row, $context);
                    $summary['found']++;

                    continue;
                }

                if ($action === 'link') {
                    $item = ShoppingItem::find($row['link_item_id'] ?? null);
                    if (! $item) {
                        throw ValidationException::withMessages(["rows.$index.link_item_id" => 'Selecciona un ingrediente válido.']);
                    }
                    $this->createAlias($item, $row['term']);
                    $this->applyShoppingState($item, $row, $context);
                    $summary['linked']++;

                    continue;
                }

                if ($action === 'create') {
                    $name = trim((string) ($row['new_name'] ?? ''));
                    $categoryId = trim((string) ($row['category_id'] ?? ''));
                    if ($name === '' || ! array_key_exists($categoryId, MealCategory::options())) {
                        throw ValidationException::withMessages(["rows.$index.new_name" => 'Completa el nombre y la categoría.']);
                    }

                    $this->assertNameAvailable($name);
                    $item = app(InventoryLedger::class)->saveProduct((int) auth()->id(), [
                        'name' => $name,
                        'stock' => 0,
                        'to_buy' => $context === 'shopping' && $row['quantity'] !== null ? (float) $row['quantity'] : 0,
                        'category_id' => $categoryId,
                        'status' => 'available',
                        'next_purchase' => $context === 'shopping',
                        'base_unit' => UnitConverter::baseUnitOf($row['unit'] ?? null),
                    ], context: ['channel' => 'import', 'reason' => 'Alta por importación']);

                    if ($this->normalize($name) !== $row['normalized']) {
                        $this->createAlias($item, $row['term']);
                    }
                    $summary['created']++;

                    continue;
                }

                throw ValidationException::withMessages(["rows.$index.action" => 'Resuelve o ignora este término antes de confirmar.']);
            }

            return $summary;
        });
    }

    public function assertNameAvailable(string $name, ?string $exceptItemId = null): void
    {
        $normalized = $this->normalize($name);
        if ($normalized === '') {
            throw ValidationException::withMessages(['name' => 'El nombre no puede quedar vacío.']);
        }

        $nameConflict = ShoppingItem::query()->get()->first(fn (ShoppingItem $item) => $item->id !== $exceptItemId && $this->normalize($item->name) === $normalized
        );
        $aliasConflict = ShoppingItemAlias::query()
            ->where('normalized_alias', $normalized)
            ->when($exceptItemId, fn ($query) => $query->where('shopping_item_id', '!=', $exceptItemId))
            ->first();

        if ($nameConflict || $aliasConflict) {
            throw ValidationException::withMessages(['name' => 'Ese nombre ya identifica otro ingrediente o alias.']);
        }
    }

    public function syncAliases(ShoppingItem $item, array $aliases): void
    {
        $cleanAliases = collect($aliases)
            ->map(fn ($alias) => trim((string) (is_array($alias) ? ($alias['alias'] ?? '') : $alias)))
            ->filter()
            ->reject(fn ($alias) => $this->normalize($alias) === $this->normalize($item->name))
            ->unique(fn ($alias) => $this->normalize($alias))
            ->values();

        foreach ($cleanAliases as $alias) {
            $this->assertAliasAvailable($alias, $item->id);
        }

        $item->aliases()->delete();
        foreach ($cleanAliases as $alias) {
            $this->createAlias($item, $alias);
        }
    }

    public function createAlias(ShoppingItem $item, string $alias): ShoppingItemAlias
    {
        $normalized = $this->assertAliasAvailable($alias, $item->id);

        return $item->aliases()->firstOrCreate(
            ['normalized_alias' => $normalized],
            ['alias' => trim($alias)]
        );
    }

    private function assertAliasAvailable(string $alias, string $itemId): string
    {
        $normalized = $this->normalize($alias);
        if ($normalized === '') {
            throw ValidationException::withMessages(['aliases' => 'Los alias no pueden estar vacíos.']);
        }

        $nameConflict = ShoppingItem::query()->get()->first(fn (ShoppingItem $item) => $item->id !== $itemId && $this->normalize($item->name) === $normalized
        );
        $aliasConflict = ShoppingItemAlias::query()
            ->where('normalized_alias', $normalized)
            ->where('shopping_item_id', '!=', $itemId)
            ->first();

        if ($nameConflict || $aliasConflict) {
            throw ValidationException::withMessages(['aliases' => 'El alias «'.trim($alias).'» ya identifica otro ingrediente.']);
        }

        return $normalized;
    }

    private function applyShoppingState(ShoppingItem $item, array $row, string $context): void
    {
        if ($context !== 'shopping') {
            return;
        }

        $changes = ['next_purchase' => true];
        if (($row['quantity'] ?? null) !== null) {
            $changes['to_buy'] = (float) $row['quantity'];
        }
        $item->update($changes);
    }
}
