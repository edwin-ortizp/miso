<?php

namespace App\Mcp\Tools\Shopping;

use App\Mcp\Tools\Shopping\Concerns\InteractsWithInventory;
use App\Mcp\Tools\Shopping\Concerns\RecordsVariantPrices;
use App\Mcp\Tools\Shopping\Concerns\ResolvesShoppingItem;
use App\Models\MealCategory;
use App\Models\ShoppingItem;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Actualiza un producto del catálogo de compras: unidad base, cantidad a comprar, categoría, stock total contado, ubicación, mínimo, nutrición, o registra una variante con su precio. Al cambiar base_unit, revisa y envía stock, min_stock y nutrición corregidos en la nueva unidad cuando sea necesario; revisa también el contenido de todas las variantes mediante update-variant-tool. No hay conversión automática entre peso, volumen y unidades: los números omitidos se conservan y el historial de compras mantiene sus unidades originales.')]
class UpdateShoppingItemTool extends Tool
{
    use InteractsWithInventory, RecordsVariantPrices, ResolvesShoppingItem;

    public function handle(Request $request): Response|ResponseFactory
    {
        $data = $request->validate([
            'item_id' => ['nullable', 'string'],
            'name' => ['nullable', 'string'],
            'quantity' => ['sometimes', 'numeric', 'min:0'],
            'category_id' => ['sometimes', 'nullable', 'string', Rule::in(array_keys(MealCategory::options()))],
            'stock' => ['sometimes', 'numeric', 'min:0'],
            'min_stock' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'grams_per_piece' => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'kcal' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'protein' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'carbs' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'fat' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            ...$this->variantPriceRules(),
        ]);

        $store = $this->priceStore($data);
        if ($store instanceof Response) {
            return $store;
        }

        $item = $this->resolveShoppingItem($data['item_id'] ?? null, $data['name'] ?? null);
        if ($item instanceof Response) {
            return $item;
        }

        $locationData = $request->validate(['storage_location_id' => $this->storageLocationRules($item)]);

        $updates = collect($data)->only(['category_id', 'stock', 'min_stock', 'grams_per_piece', 'kcal', 'protein', 'carbs', 'fat'])->all();
        $updates = [...$updates, ...$locationData];
        if (array_key_exists('quantity', $data)) {
            $updates['to_buy'] = $data['quantity'];
        }

        if (filled($data['base_unit'] ?? null)) {
            $baseUnit = $this->resolveBaseUnit($data['base_unit']);
            if (! $baseUnit) {
                return Response::error('base_unit debe ser g, ml o unit.');
            }
            $updates['base_unit'] = $baseUnit;
        }

        if ($updates !== []) {
            $item->update($updates);
        }

        $recorded = $this->recordVariantPrice($item->refresh(), $data, $store);

        if ($updates === [] && ! $recorded) {
            return Response::error('No se indicó ningún campo para actualizar.');
        }

        return Response::structured(['item' => $this->inventorySummary($item->refresh()), 'message' => "Ítem \"{$item->name}\" actualizado (id: {$item->id})".($recorded ? "; {$recorded}" : '').'.']);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'item_id' => $schema->string()
                ->description('Identificador (UUID) del ítem. Alternativa a "name".'),
            'name' => $schema->string()
                ->description('Nombre del ítem. Alternativa a "item_id".'),
            'quantity' => $schema->number()
                ->description('Nueva cantidad de paquetes a comprar.'),
            'category_id' => $schema->string()
                ->description('Identificador de categoría del usuario. Consulta category_options en ListShoppingItemsTool.'),
            'storage_location_id' => $schema->string()->nullable()->description('UUID de ubicación activa propia; null para quitarla. Omitir conserva la actual, incluso archivada.'),
            'stock' => $schema->number()->description('Total contado en casa en la unidad base. Reemplaza el stock, no suma ni registra compras. Omitir lo conserva.'),
            'min_stock' => $schema->number()->description('Stock mínimo, en la unidad base.'),
            'grams_per_piece' => $schema->number()->description('Gramos por pieza para productos por peso que se cuentan por pieza.'),
            'kcal' => $schema->number()->description('Calorías por 100 g, por 100 ml o por unidad según la unidad base.'),
            'protein' => $schema->number()->description('Proteína (g) por 100 g, 100 ml o unidad.'),
            'carbs' => $schema->number()->description('Carbohidratos (g) por 100 g, 100 ml o unidad.'),
            'fat' => $schema->number()->description('Grasa (g) por 100 g, 100 ml o unidad.'),
            ...$this->variantPriceSchema($schema),
            'base_unit' => $schema->string()->enum(array_keys(ShoppingItem::BASE_UNITS))
                ->description('Nueva unidad base: g, ml o unit. Puede cambiar al editar. Envía también stock, min_stock y nutrición corregidos si corresponde y actualiza los contenidos de las variantes con update-variant-tool. Los números omitidos se conservan; no se deducen equivalencias ni se modifica el historial de compras.'),
        ];
    }
}
