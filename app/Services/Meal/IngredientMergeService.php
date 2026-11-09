<?php

namespace App\Services\Meal;

use App\Models\MealInventoryMovement;
use App\Models\PurchaseLine;
use App\Models\RecipeIngredient;
use App\Models\ShoppingItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Convierte un producto en variante de otro: «Aromática de manzanilla» pasa a ser la variante «manzanilla» de «Aromática».
 * Mueve variantes (con sus precios), stock, compras, movimientos de inventario, recetas y alias, y elimina el producto origen.
 */
class IngredientMergeService
{
    public function __construct(private IngredientImportService $importer) {}

    /** Diferencia entre nombres: «Aromática de manzanilla» respecto a «Aromática» → «manzanilla». */
    public static function suggestedFlavor(string $source, string $target): string
    {
        if (! str_starts_with(CatalogNames::normalize($source), CatalogNames::normalize($target).' ')) {
            return '';
        }

        $rest = trim(mb_substr(trim($source), mb_strlen(trim($target))));

        return trim(preg_replace('/^(de|con|en|sin)\s+/iu', '', $rest) ?? $rest);
    }

    /** Producto cuyo nombre es el comienzo más largo del nombre del origen («Aromática» para «Aromática de manzanilla»). */
    public static function suggestedTarget(ShoppingItem $source): ?ShoppingItem
    {
        $name = CatalogNames::normalize($source->name);

        return ShoppingItem::where('id', '!=', $source->id)->get(['id', 'name', 'base_unit'])
            ->filter(fn (ShoppingItem $item) => str_starts_with($name, CatalogNames::normalize($item->name).' '))
            ->sortByDesc(fn (ShoppingItem $item) => mb_strlen($item->name))
            ->first();
    }

    /** @return array{variants: int, prices: int, purchases: int, recipes: int, stock: float} */
    public function preview(ShoppingItem $source): array
    {
        return [
            'variants' => $source->variants()->count(),
            'prices' => $source->prices()->count(),
            'purchases' => PurchaseLine::where('shopping_item_id', $source->id)->count(),
            'recipes' => RecipeIngredient::where('shopping_item_id', $source->id)->count(),
            'stock' => (float) $source->stock,
        ];
    }

    public function merge(ShoppingItem $source, ShoppingItem $target, ?string $flavor, bool $keepAlias = true): ShoppingItem
    {
        if ($source->is($target)) {
            throw ValidationException::withMessages(['mergeTargetId' => 'Elige un producto distinto.']);
        }
        if ($source->base_unit && $target->base_unit && $source->base_unit !== $target->base_unit) {
            throw ValidationException::withMessages(['mergeTargetId' => 'Los dos productos deben usar la misma unidad base ('.$source->baseUnitLabel().' y '.$target->baseUnitLabel().').']);
        }

        // Suggested targets intentionally project only id/name/base_unit. Ownership is
        // established again by the two scoped, locked rows below, never by that projection.
        $userId = (int) ($source->user_id ?? auth()->id());
        if (! $userId || (auth()->check() && $userId !== (int) auth()->id())) {
            throw ValidationException::withMessages(['mergeTargetId' => 'Los productos deben pertenecer al mismo usuario.']);
        }
        $flavor = trim((string) $flavor) ?: null;
        DB::transaction(function () use ($source, $target, $flavor, $keepAlias, $userId) {
            User::withoutGlobalScopes()->whereKey($userId)->lockForUpdate()->firstOrFail();
            $locked = ShoppingItem::withoutGlobalScopes()->where('user_id', $userId)->whereIn('id', [$source->id, $target->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($locked->count() !== 2) {
                throw ValidationException::withMessages(['mergeTargetId' => 'Un producto ya no está disponible.']);
            }
            $source = $locked[$source->id];
            $target = $locked[$target->id];
            app(InventoryLedger::class)->transfer($userId, $source->id, $target->id,
                ['reason' => 'Fusión de ingredientes', 'channel' => request()->is('mcp*', 'miso', 'life-tracker') ? 'mcp' : (app()->runningInConsole() ? 'system' : 'web')]);
            // Variantes y sus precios: el sabor nuevo va delante del que ya tuvieran.
            $variants = $source->variants()->get();
            foreach ($variants as $variant) {
                $variant->update([
                    'shopping_item_id' => $target->id,
                    'flavor' => collect([$flavor, $variant->flavor])->filter()->unique()->implode(' · ') ?: null,
                    // Con unidades base distintas el contenido no es comparable.
                    'content' => $source->base_unit === $target->base_unit ? $variant->content : null,
                    'is_preferred' => false,
                ]);
            }
            if ($variants->isEmpty() && $flavor) {
                $target->variants()->create(['flavor' => $flavor]);
            }

            // Recetas: si ya usan el principal se suman cantidades con la misma unidad.
            foreach (RecipeIngredient::where('shopping_item_id', $source->id)->get() as $usage) {
                $existing = RecipeIngredient::where('recipe_id', $usage->recipe_id)->where('shopping_item_id', $target->id)->first();
                if (! $existing) {
                    $usage->update(['shopping_item_id' => $target->id]);

                    continue;
                }
                if ($existing->unit !== $usage->unit) {
                    throw ValidationException::withMessages(['mergeTargetId' => 'Una receta usa los dos productos con unidades distintas; ajústala antes de combinarlos.']);
                }
                $existing->update(['quantity' => (float) $existing->quantity + (float) $usage->quantity]);
                $usage->delete();
            }

            PurchaseLine::where('shopping_item_id', $source->id)->update(['shopping_item_id' => $target->id]);
            foreach (MealInventoryMovement::withoutGlobalScopes()->where('user_id', $userId)->where('shopping_item_id', $source->id)->get() as $movement) {
                if ($movement->historical_product_id === null) {
                    $movement->update(['historical_product_id' => $source->id]);
                }
                $movement->update(['shopping_item_id' => null]);
            }

            $target->update([
                'to_buy' => max((float) $target->to_buy, (float) $source->to_buy),
                'next_purchase' => $target->next_purchase || $source->next_purchase,
                'base_unit' => $target->base_unit ?? $source->base_unit,
            ]);

            $aliases = $source->aliases()->pluck('alias');
            $source->aliases()->delete();
            $source->delete();

            foreach ($keepAlias ? $aliases->push($source->name) : $aliases as $alias) {
                if (CatalogNames::normalize($alias) !== CatalogNames::normalize($target->name)) {
                    $this->importer->createAlias($target, $alias);
                }
            }
        });

        app(RecipeCalculator::class)->refreshRecipesUsing($target->fresh());

        return $target->fresh();
    }
}
