<?php

namespace App\Services\Meal;

use App\Models\MealInventoryMovement;
use App\Models\MealInventoryOperation;
use App\Models\MealPlanEntry;
use App\Models\MealPlanEntryItem;
use App\Models\MealPreparation;
use App\Models\Recipe;
use App\Models\ShoppingItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** The single write boundary for meals, reservations and their inventory ledger. */
class MealInventory
{
    public const TYPES = ['desayuno', 'almuerzo', 'comida', 'merienda', 'cena'];

    public function fail(string $message): never
    {
        throw ValidationException::withMessages(['inventory' => $message]);
    }

    private function operation(int $userId, string $action, array $input, ?string $key, callable $callback): array
    {
        $key ??= (string) Str::uuid();
        Validator::make(['key' => $key], ['key' => ['required', 'string', 'max:100']])->validate();

        return DB::transaction(function () use ($userId, $action, $input, $key, $callback) {
            // All meal writers take this lock first, including reservations with no stock movement.
            User::whereKey($userId)->lockForUpdate()->firstOrFail();
            $fingerprint = hash('sha256', json_encode([$action, $this->canonicalInput($input)], JSON_THROW_ON_ERROR));
            $existing = MealInventoryOperation::where('user_id', $userId)->where('operation_key', $key)->first();
            if ($existing) {
                if ($existing->fingerprint !== $fingerprint) {
                    $this->fail('La clave de operación ya se utilizó con otros datos.');
                }

                return $existing->result;
            }
            $operation = MealInventoryOperation::create(['user_id' => $userId, 'operation_key' => $key, 'action' => $action, 'fingerprint' => $fingerprint]);
            $result = $callback($operation);
            $result['operation_id'] = $operation->id;
            $result = json_decode(json_encode($result, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
            $operation->update(['result' => $result]);

            return $result;
        }, 3);
    }

    public function entry(int $userId, int $id): MealPlanEntry
    {
        return MealPlanEntry::where('user_id', $userId)->with(['items.recipe.recipeIngredients.shoppingItem', 'items.preparation'])->find($id)
            ?? $this->fail('No se encontró la comida o no te pertenece.');
    }

    private function canonicalInput(array $input): array
    {
        if (! array_is_list($input)) {
            ksort($input);
        }
        foreach ($input as &$value) {
            if (is_array($value)) {
                $value = $this->canonicalInput($value);
            }
        }

        return $input;
    }

    private function planned(MealPlanEntry $entry): void
    {
        if ($entry->status !== 'planned') {
            $this->fail('La comida ya está consumida. Revierte el consumo antes de editar, trasladar o borrar.');
        }
    }

    private function slot(int $userId, string $date, string $type): ?MealPlanEntry
    {
        return MealPlanEntry::where('user_id', $userId)->whereDate('date', $date)->where('meal_type', $type)->first();
    }

    public function normalizeItems(int $userId, array $items): array
    {
        Validator::make(['items' => $items], [
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.name' => ['nullable', 'string', 'max:255'],
            'items.*.recipe_id' => ['nullable', 'uuid'],
            'items.*.preparation_id' => ['nullable', 'uuid'],
            'items.*.portions' => ['nullable', 'numeric', 'min:0.01', 'max:999999'],
            'items.*.calories' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'items.*.ingredients' => ['nullable', 'array', 'max:100'],
            'items.*.ingredients.*.shopping_item_id' => ['required', 'uuid'],
            'items.*.ingredients.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'items.*.ingredients.*.unit' => ['nullable', 'string', 'max:50'],
        ])->validate();

        return collect($items)->map(function ($row) use ($userId) {
            if (! empty($row['recipe_id']) && ! empty($row['preparation_id'])) {
                $this->fail('Un componente debe ser receta o preparación, no ambas.');
            }
            $recipe = empty($row['recipe_id']) ? null : Recipe::where('user_id', $userId)->find($row['recipe_id']);
            $preparation = empty($row['preparation_id']) ? null : MealPreparation::where('user_id', $userId)->lockForUpdate()->find($row['preparation_id']);
            if ((! empty($row['recipe_id']) && ! $recipe) || (! empty($row['preparation_id']) && (! $preparation || $preparation->cancelled))) {
                $this->fail('La receta o preparación no está disponible o no te pertenece.');
            }
            if (($recipe || $preparation) && ! empty($row['ingredients'])) {
                $this->fail('Los ingredientes enlazados pertenecen a elementos libres; las recetas y preparaciones tienen sus propios ingredientes.');
            }
            $name = $recipe?->name ?? $preparation?->name ?? trim($row['name'] ?? '');
            if ($name === '') {
                $this->fail('Escribe el nombre del elemento libre.');
            }
            $ingredients = [];
            foreach ($row['ingredients'] ?? [] as $ingredient) {
                $product = ShoppingItem::where('user_id', $userId)->find($ingredient['shopping_item_id']);
                if (! $product || ! $product->base_unit) {
                    $this->fail('Un ingrediente no está disponible, no te pertenece o no tiene unidad base.');
                }
                $quantity = UnitConverter::toBase((float) $ingredient['quantity'], $ingredient['unit'] ?? null, $product->base_unit, $product->grams_per_piece);
                if ($quantity === null || $quantity <= 0) {
                    $this->fail("Cantidad o unidad incompatible para {$product->name} ({$product->base_unit}).");
                }
                $ingredients[] = ['shopping_item_id' => $product->id, 'name' => $product->name, 'quantity' => $quantity, 'unit' => $product->base_unit];
            }

            return ['id' => $row['id'] ?? null, 'recipe_id' => $recipe?->id, 'preparation_id' => $preparation?->id,
                'name' => $recipe ? null : $name, 'portions' => ($recipe || $preparation) ? round((float) ($row['portions'] ?? 1), 2) : null,
                'calories' => ($recipe || $preparation) ? null : ($row['calories'] ?? null), 'ingredients' => $ingredients ?: null];
        })->all();
    }

    private function reservations(int $userId): void
    {
        foreach (MealPreparation::where('user_id', $userId)->where('cancelled', false)->lockForUpdate()->get() as $preparation) {
            if ($preparation->reserved() > $preparation->remaining() + 0.00001) {
                $this->fail("No hay porciones suficientes de {$preparation->name}. Disponibles: {$preparation->remaining()}; reservadas: {$preparation->reserved()}.");
            }
        }
    }

    public function plan(int $userId, array $data, ?string $key = null): array
    {
        Validator::make($data, ['date' => ['required', 'date'], 'meal_type' => ['required', Rule::in(self::TYPES)], 'mode' => ['nullable', Rule::in(['append', 'replace'])], 'notes' => ['nullable', 'string', 'max:5000'], 'calories' => ['nullable', 'integer', 'min:0'], 'scheduled_time' => ['nullable', 'date_format:H:i'], 'duration_minutes' => ['nullable', 'integer', 'between:1,1440'], 'entry_id' => ['nullable', 'integer']])->validate();

        return $this->operation($userId, 'plan', $data, $key, function () use ($userId, $data) {
            $date = Carbon::parse($data['date'])->toDateString();
            $entry = empty($data['entry_id']) ? $this->slot($userId, $date, $data['meal_type']) : $this->entry($userId, (int) $data['entry_id']);
            if ($entry) {
                $this->planned($entry);
                if ($entry->date->toDateString() !== $date || $entry->meal_type !== $data['meal_type']) {
                    $this->fail('Usa la acción mover para cambiar la fecha o el tipo de una comida existente.');
                }
            } else {
                $entry = MealPlanEntry::create(['user_id' => $userId, 'date' => $date, 'meal_type' => $data['meal_type'], 'status' => 'planned']);
            }
            $entry->fill(array_intersect_key($data, array_flip(['scheduled_time', 'duration_minutes'])))->save();
            $rows = $this->normalizeItems($userId, $data['items']);
            $replace = ($data['mode'] ?? 'append') === 'replace';
            $old = $entry->items()->where('user_id', $userId)->get()->keyBy('id');
            if (! $replace && collect($rows)->contains(fn ($row) => $row['id'] !== null)) {
                $this->fail('Para editar componentes existentes usa mode=replace con el conjunto completo.');
            }
            if ($replace && collect($rows)->pluck('recipe_id')->filter()->duplicates()->isNotEmpty()) {
                $this->fail('Una receta no puede repetirse dentro de la misma comida. Ajusta sus porciones.');
            }
            if ($replace) {
                // Release recipe uniqueness while existing components exchange recipes.
                $entry->items()->where('user_id', $userId)->update(['recipe_id' => null]);
                $old->each(fn ($item) => $item->setAttribute('recipe_id', null)->syncOriginalAttribute('recipe_id'));
            }
            $keep = [];
            foreach ($rows as $position => $row) {
                $id = $row['id'];
                unset($row['id']);
                if ($id && (! $old->has($id) || in_array($id, $keep, true))) {
                    $this->fail('Identificador de componente ajeno, repetido o inexistente.');
                }
                $item = $id ? $old[$id] : null;
                if (! $replace && ! $item && $row['recipe_id']) {
                    $item = $entry->items()->where('recipe_id', $row['recipe_id'])->first();
                    if ($item) {
                        $row['portions'] += (float) $item->portions;
                    }
                }
                $row['position'] = $replace ? $position : ($item?->position ?? $old->count() + $position);
                $item ? $item->update($row) : $item = $entry->items()->create($row + ['user_id' => $userId]);
                $keep[] = $item->id;
            }
            if ($replace) {
                $entry->items()->whereNotIn('id', $keep)->delete();
            }
            $updates = [];
            foreach (['notes', 'calories'] as $field) {
                if ($replace || array_key_exists($field, $data)) {
                    $updates[$field] = $field === 'notes' && ! $replace && $entry->notes && filled($data[$field] ?? null)
                        ? $entry->notes."\n".$data[$field] : ($data[$field] ?? null);
                }
            }
            $entry->update($updates);
            $this->reservations($userId);

            return ['meal_id' => $entry->id, 'status' => 'planned'];
        });
    }

    /** Canonical product quantities; null quantity marks incomplete data in read-only previews. */
    public function recipeIngredients(int $userId, Recipe $recipe, float $portions, bool $strict = true): array
    {
        $factor = $portions / max((float) ($recipe->servings ?: 1), 0.01);
        $rows = [];
        foreach ($recipe->recipeIngredients()->where('user_id', $userId)->with('shoppingItem')->get() as $ingredient) {
            $product = $ingredient->shoppingItem;
            $quantity = $product && (int) $product->user_id === $userId && $product->base_unit && $ingredient->quantity !== null
                ? UnitConverter::toBase((float) $ingredient->quantity * $factor, $ingredient->unit, $product->base_unit, $product->grams_per_piece) : null;
            if ($strict && ($quantity === null || $quantity <= 0)) {
                $this->fail('Cantidad o unidad incompleta/incompatible en '.$recipe->name.': '.($product?->name ?? 'producto no disponible').'.');
            }
            $rows[] = ['shopping_item_id' => $product?->id, 'name' => $product?->name ?? 'Producto no disponible', 'quantity' => $quantity, 'unit' => $product?->base_unit];
        }
        if ($strict && $rows === []) {
            $this->fail("La receta {$recipe->name} no tiene ingredientes enlazados.");
        }

        return $rows;
    }

    public function entryIngredients(int $userId, MealPlanEntry $entry, bool $strict = true): array
    {
        $rows = [];
        foreach ($entry->items as $item) {
            if ($item->preparation_id) {
                continue;
            }
            if ($item->recipe_id) {
                $recipe = Recipe::where('user_id', $userId)->find($item->recipe_id) ?? $this->fail('Receta no disponible.');
                $rows = array_merge($rows, $this->recipeIngredients($userId, $recipe, (float) $item->portions, $strict));
            } else {
                foreach ($item->ingredients ?? [] as $row) {
                    $product = ShoppingItem::where('user_id', $userId)->find($row['shopping_item_id']);
                    $quantity = $product && $product->base_unit === $row['unit'] ? $row['quantity'] : null;
                    if ($strict && $quantity === null) {
                        $this->fail('Producto no disponible o unidad base modificada en '.$row['name'].'. Revisa los ingredientes antes de consumir.');
                    }
                    $rows[] = array_replace($row, ['quantity' => $quantity]);
                }
            }
        }

        return $rows;
    }

    private function debit(MealInventoryOperation $operation, array $rows): void
    {
        $errors = [];
        foreach (collect($rows)->groupBy('shopping_item_id')->sortKeys() as $id => $ingredients) {
            $product = ShoppingItem::where('user_id', $operation->user_id)->lockForUpdate()->find($id);
            $quantity = round($ingredients->sum('quantity'), 3);
            if (! $product || $ingredients->contains(fn ($row) => $row['unit'] !== $product->base_unit || $row['quantity'] === null)) {
                $errors[] = 'Producto o unidad incompatible: '.$ingredients->first()['name'];
            } elseif ($product->stock + 0.00001 < $quantity) {
                $errors[] = "{$product->name}: faltan ".round($quantity - $product->stock, 3)." {$product->base_unit}";
            } else {
                $product->update(['stock' => round($product->stock - $quantity, 3)]);
                $operation->movements()->create(['user_id' => $operation->user_id, 'shopping_item_id' => $product->id, 'name' => $product->name, 'unit' => $product->base_unit, 'delta' => -$quantity]);
            }
        }
        if ($errors) {
            $this->fail(implode('; ', $errors));
        }
    }

    public function cook(int $userId, array $data, ?string $key = null): array
    {
        Validator::make($data, ['recipe_id' => ['required', 'uuid'], 'portions' => ['required', 'numeric', 'min:0.01', 'max:999999'], 'cooked_at' => ['nullable', 'date'], 'consume_by' => ['nullable', 'date']])->validate();

        return $this->operation($userId, 'cook', $data, $key, function ($operation) use ($userId, $data) {
            $recipe = Recipe::where('user_id', $userId)->find($data['recipe_id']) ?? $this->fail('Receta no disponible o ajena.');
            $portions = round((float) $data['portions'], 2);
            $ingredients = $this->recipeIngredients($userId, $recipe, $portions);
            $this->debit($operation, $ingredients);
            $preparation = MealPreparation::create(['user_id' => $userId, 'recipe_id' => $recipe->id, 'operation_id' => $operation->id,
                'name' => $recipe->name, 'cooked_at' => $data['cooked_at'] ?? now(), 'consume_by' => $data['consume_by'] ?? null,
                'portions' => $portions, 'ingredients' => $ingredients, 'nutrition' => $recipe->nutrition]);

            return ['preparation_id' => $preparation->id, 'portions' => $portions, 'warnings' => $this->expiryWarnings($userId, $ingredients)];
        });
    }

    public function consume(int $userId, int $id, array $data = [], ?string $key = null): array
    {
        Validator::make($data, ['mode' => ['nullable', Rule::in(['home', 'outside'])], 'consumed_at' => ['nullable', 'date'], 'notes' => ['nullable', 'string', 'max:5000'], 'calories' => ['nullable', 'integer', 'min:0']])->validate();

        return $this->operation($userId, 'consume', ['meal_id' => $id] + $data, $key, function ($operation) use ($userId, $id, $data) {
            $entry = $this->entry($userId, $id);
            $this->planned($entry);
            $outside = ($data['mode'] ?? 'home') === 'outside';
            $warnings = [];
            if (! $outside) {
                $ingredients = $this->entryIngredients($userId, $entry);
                $this->debit($operation, $ingredients);
                $warnings = $this->expiryWarnings($userId, $ingredients);
                foreach ($entry->items->whereNotNull('preparation_id')->groupBy('preparation_id')->sortKeys() as $prepId => $items) {
                    $prep = MealPreparation::where('user_id', $userId)->lockForUpdate()->find($prepId) ?? $this->fail('Preparación ajena o inexistente.');
                    $portions = (float) $items->sum('portions');
                    if ($prep->cancelled || $prep->remaining() + 0.00001 < $portions) {
                        $this->fail("No hay porciones suficientes de {$prep->name}.");
                    }
                    $operation->movements()->create(['user_id' => $userId, 'preparation_id' => $prep->id, 'name' => $prep->name, 'unit' => 'portion', 'delta' => -$portions]);
                    if ($prep->consume_by && $prep->consume_by->lte(today()->addDays(7))) {
                        $warnings[] = $prep->name.': fecha límite '.$prep->consume_by->toDateString().'.';
                    }
                }
                if ($entry->items->contains(fn ($item) => ! $item->recipe_id && ! $item->preparation_id && empty($item->ingredients))) {
                    $warnings[] = 'Inventario incompleto: hay elementos libres sin ingredientes enlazados; no se descontaron.';
                }
            }
            $snapshot = ['items' => $entry->items->map(fn ($item) => ['id' => $item->id, 'name' => $item->recipe?->name ?? $item->name, 'recipe_id' => $item->recipe_id, 'preparation_id' => $item->preparation_id, 'portions' => $item->portions])->all(),
                'calories' => $data['calories'] ?? ($outside ? null : $entry->effective_calories), 'notes' => $data['notes'] ?? null, 'warnings' => $warnings];
            $entry->update(['status' => 'consumed', 'consumption_mode' => $outside ? 'outside' : 'home', 'consumed_at' => $data['consumed_at'] ?? now(), 'consumption_operation_id' => $operation->id, 'consumption' => $snapshot]);
            $this->reservations($userId);

            return ['meal_id' => $id, 'status' => 'consumed', 'mode' => $entry->consumption_mode, 'warnings' => $warnings];
        });
    }

    public function consumeAt(int $userId, array $data, ?string $key = null): array
    {
        Validator::make($data, ['date' => ['required', 'date'], 'meal_type' => ['required', Rule::in(self::TYPES)], 'mode' => ['required', 'in:outside']])->validate();

        return $this->operation($userId, 'consume_at', $data, $key, function ($operation) use ($userId, $data) {
            $date = Carbon::parse($data['date'])->toDateString();
            $entry = $this->slot($userId, $date, $data['meal_type']) ?? MealPlanEntry::create(['user_id' => $userId, 'date' => $date, 'meal_type' => $data['meal_type'], 'status' => 'planned']);

            return $this->consume($userId, $entry->id, array_intersect_key($data, array_flip(['mode', 'notes', 'calories', 'consumed_at'])), $operation->id.':consume');
        });
    }

    private function expiryWarnings(int $userId, array $ingredients): array
    {
        return ShoppingItem::where('user_id', $userId)->whereIn('id', collect($ingredients)->pluck('shopping_item_id'))->whereNotNull('consume_by')
            ->whereDate('consume_by', '<=', today()->addDays(7)->toDateString())->get()
            ->map(fn ($product) => $product->name.': fecha límite '.$product->consume_by->toDateString().'.')->all();
    }

    private function reverse(MealInventoryOperation $original, MealInventoryOperation $operation): void
    {
        if ($original->reversed) {
            $this->fail('La operación ya fue revertida.');
        }
        foreach ($original->movements()->where('user_id', $operation->user_id)->orderBy('shopping_item_id')->get() as $movement) {
            if ($movement->shopping_item_id) {
                $product = ShoppingItem::where('user_id', $operation->user_id)->lockForUpdate()->find($movement->shopping_item_id);
                if (! $product || $product->base_unit !== $movement->unit) {
                    $this->fail("La unidad base de {$movement->name} cambió. Resuelve la incompatibilidad antes de revertir.");
                }
                $product->update(['stock' => round($product->stock - $movement->delta, 3)]);
            }
            $operation->movements()->create(['user_id' => $operation->user_id, 'shopping_item_id' => $movement->shopping_item_id, 'preparation_id' => $movement->preparation_id,
                'name' => $movement->name, 'unit' => $movement->unit, 'delta' => -$movement->delta]);
        }
        $original->update(['reversed' => true]);
    }

    public function revert(int $userId, int $id, ?string $key = null): array
    {
        return $this->operation($userId, 'revert', ['meal_id' => $id], $key, function ($operation) use ($userId, $id) {
            $entry = $this->entry($userId, $id);
            if ($entry->status !== 'consumed') {
                $this->fail('La comida no está consumida.');
            }
            $original = MealInventoryOperation::where('user_id', $userId)->findOrFail($entry->consumption_operation_id);
            $this->reverse($original, $operation);
            $entry->update(['status' => 'planned', 'consumption_mode' => null, 'consumed_at' => null, 'consumption_operation_id' => null, 'consumption' => null]);
            $this->reservations($userId);

            return ['meal_id' => $id, 'status' => 'planned'];
        });
    }

    public function cancelPreparation(int $userId, string $id, ?string $key = null): array
    {
        return $this->operation($userId, 'cancel_preparation', ['preparation_id' => $id], $key, function ($operation) use ($userId, $id) {
            $prep = MealPreparation::where('user_id', $userId)->lockForUpdate()->find($id) ?? $this->fail('Preparación no disponible o ajena.');
            if ($prep->cancelled || $prep->reserved() > 0 || abs($prep->remaining() - $prep->portions) > 0.00001) {
                $this->fail('Solo se puede cancelar una preparación sin consumos ni reservas.');
            }
            $this->reverse(MealInventoryOperation::where('user_id', $userId)->findOrFail($prep->operation_id), $operation);
            $prep->update(['cancelled' => true]);

            return ['preparation_id' => $id, 'cancelled' => true];
        });
    }

    public function rearrange(int $userId, int $id, string $action, array $target = [], ?string $key = null): array
    {
        Validator::make(['action' => $action] + $target, ['action' => ['required', Rule::in(['move', 'copy', 'swap', 'delete'])], 'date' => ['required_unless:action,delete', 'date'], 'meal_type' => ['required_unless:action,delete', Rule::in(self::TYPES)]])->validate();

        return $this->operation($userId, $action, ['meal_id' => $id] + $target, $key, function () use ($userId, $id, $action, $target) {
            $source = $this->entry($userId, $id);
            $this->planned($source);
            if ($action === 'delete') {
                $source->delete();

                return ['meal_id' => $id, 'deleted' => true];
            }
            $date = Carbon::parse($target['date'])->toDateString();
            $destination = $this->slot($userId, $date, $target['meal_type']);
            if ($destination?->id === $id) {
                $this->fail('El destino debe ser una casilla diferente.');
            }
            if ($destination) {
                $this->planned($destination);
            }
            if ($action === 'swap') {
                if (! $destination) {
                    $this->fail('Para intercambiar elige una casilla con comida.');
                }
                $sourceIds = $source->items->pluck('id');
                $destinationItems = $destination->items()->get();
                $destinationIds = $destinationItems->pluck('id');
                $allItems = $source->items->concat($destinationItems);
                MealPlanEntryItem::where('user_id', $userId)->whereIn('id', $allItems->pluck('id'))->update(['recipe_id' => null]);
                MealPlanEntryItem::where('user_id', $userId)->whereIn('id', $sourceIds)->update(['meal_plan_entry_id' => $destination->id]);
                MealPlanEntryItem::where('user_id', $userId)->whereIn('id', $destinationIds)->update(['meal_plan_entry_id' => $source->id]);
                foreach ($allItems as $item) {
                    MealPlanEntryItem::where('user_id', $userId)->whereKey($item->id)->update(['recipe_id' => $item->recipe_id]);
                }
                $header = $source->only(['notes', 'calories', 'scheduled_time', 'duration_minutes']);
                $source->update($destination->only(['notes', 'calories', 'scheduled_time', 'duration_minutes']));
                $destination->update($header);
            } elseif (! $destination && $action === 'move') {
                $source->update(['date' => $date, 'meal_type' => $target['meal_type']]);
                $destination = $source;
            } else {
                $newDestination = ! $destination;
                $destination ??= MealPlanEntry::create(['user_id' => $userId, 'date' => $date, 'meal_type' => $target['meal_type'], 'status' => 'planned']);
                if ($newDestination) {
                    $destination->update($source->only(['scheduled_time', 'duration_minutes']));
                }
                $offset = $destination->items()->count();
                foreach ($source->items as $index => $item) {
                    $attributes = $item->only(['recipe_id', 'preparation_id', 'name', 'portions', 'calories', 'ingredients']);
                    $sameRecipe = $item->recipe_id ? $destination->items()->where('recipe_id', $item->recipe_id)->first() : null;
                    if ($sameRecipe) {
                        $sameRecipe->update(['portions' => (float) $sameRecipe->portions + (float) $item->portions]);
                        if ($action === 'move') {
                            $item->delete();
                        }
                    } elseif ($action === 'move') {
                        $item->update(['meal_plan_entry_id' => $destination->id, 'position' => $offset + $index]);
                    } else {
                        $destination->items()->create($attributes + ['user_id' => $userId, 'position' => $offset + $index]);
                    }
                }
                $destination->update(['notes' => trim(implode("\n", array_filter([$destination->notes, $source->notes]))) ?: null,
                    'calories' => $newDestination ? $source->calories : ($destination->calories !== null && $source->calories !== null ? $destination->calories + $source->calories : null)]);
                if ($action === 'move') {
                    $source->delete();
                }
            }
            $this->reservations($userId);

            return ['meal_id' => $destination->id, 'source_id' => $id, 'action' => $action];
        });
    }

    public function generateShopping(int $userId, string $since, string $until, array $variants = [], ?string $key = null): array
    {
        Validator::make(compact('since', 'until'), ['since' => ['required', 'date'], 'until' => ['required', 'date', 'after_or_equal:since']])->validate();

        return $this->operation($userId, 'generate_shopping', compact('since', 'until', 'variants'), $key, function () use ($userId, $since, $until, $variants) {
            $preview = app(MealNeeds::class)->preview($userId, $since, $until);
            $updated = [];
            foreach ($preview['needs'] as $row) {
                if ($row['missing'] === null) {
                    $this->fail('Necesidades incompletas para '.$row['name'].'. Corrige las cantidades antes de generar compras.');
                }
                if ($row['missing'] <= 0) {
                    continue;
                }
                $product = ShoppingItem::where('user_id', $userId)->lockForUpdate()->findOrFail($row['shopping_item_id']);
                $variantId = $variants[$product->id] ?? $row['variant_id'];
                $variant = $product->variants()->where('user_id', $userId)->find($variantId);
                if (! $variant || ! $variant->isComparable()) {
                    $this->fail('Elige una presentación comparable para '.$product->name.'.');
                }
                $packages = (int) ceil($row['missing'] / $variant->content);
                $product->update(['next_purchase' => true, 'to_buy' => max((float) $product->to_buy, $packages)]);
                $updated[] = ['shopping_item_id' => $product->id, 'packages' => $product->to_buy];
            }

            return ['updated' => $updated, 'warnings' => $preview['warnings']];
        });
    }
}
