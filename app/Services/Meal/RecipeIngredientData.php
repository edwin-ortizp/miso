<?php

namespace App\Services\Meal;

use App\Models\ShoppingItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Normaliza los ingredientes que llegan a una receta: producto del catálogo y cantidad en su unidad base.
 */
class RecipeIngredientData
{
    /** Producto con ese nombre, o uno nuevo cuya unidad base se infiere de la unidad escrita. */
    public static function product(string $name, ?string $unit = null): ShoppingItem
    {
        return DB::transaction(function () use ($name, $unit) {
            User::whereKey(auth()->id())->lockForUpdate()->firstOrFail();

            return ShoppingItem::where('name', trim($name))->first()
                ?? app(InventoryLedger::class)->saveProduct((int) auth()->id(), ['name' => trim($name), 'status' => 'available', 'stock' => 0, 'to_buy' => 0, 'base_unit' => UnitConverter::baseUnitOf($unit)]);
        });
    }

    /**
     * Cantidad y unidad a guardar: en la unidad base del producto cuando se puede convertir;
     * si no, tal como se escribió (la receta queda sin calcular hasta corregirla).
     *
     * @return array{0: float, 1: ?string}
     */
    public static function quantity(ShoppingItem $product, float $quantity, ?string $unit): array
    {
        $unit = filled($unit) ? trim($unit) : null;
        if (! $product->base_unit) {
            return [$quantity, $unit];
        }

        $converted = UnitConverter::toBase($quantity, $unit === $product->base_unit ? null : $unit, $product->base_unit, $product->grams_per_piece);

        return $converted === null ? [$quantity, $unit] : [$converted, $product->base_unit];
    }
}
