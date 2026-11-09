<?php

namespace App\Mcp\Tools\Shopping;

use App\Mcp\Tools\Shopping\Concerns\RecordsVariantPrices;
use App\Mcp\Tools\Shopping\Concerns\ResolvesShoppingItem;
use App\Models\MealCategory;
use App\Services\Meal\CatalogNames;
use App\Services\Meal\InventoryLedger;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Añade un producto a la lista de compras del usuario autenticado. Si el producto ya existe en el catálogo lo agrega a la lista; si no existe, lo crea (requiere base_unit). Opcionalmente registra una variante (marca, empaque, contenido) y su precio con tienda, fecha y fuente. El nombre del producto va sin marca ni tamaño.')]
class AddShoppingItemTool extends Tool
{
    use RecordsVariantPrices, ResolvesShoppingItem;

    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'item_id' => ['nullable', 'string'],
            'name' => ['nullable', 'string', 'max:255'],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
            'category_id' => ['nullable', 'string', Rule::in(array_keys(MealCategory::options()))],
            ...$this->variantPriceRules(),
        ]);

        $store = $this->priceStore($data);
        if ($store instanceof Response) {
            return $store;
        }

        if (empty($data['item_id']) && empty($data['name'])) {
            return Response::error('Debes indicar item_id o name para identificar el ítem.');
        }

        $item = null;
        $created = false;
        $baseUnit = $this->resolveBaseUnit($data['base_unit'] ?? null);
        if (filled($data['base_unit'] ?? null) && ! $baseUnit) {
            return Response::error('base_unit debe ser g, ml o unit.');
        }

        if (! empty($data['item_id'])) {
            $item = Auth::user()->shoppingItems()->find($data['item_id']);
            if (! $item) {
                return Response::error('No se encontró el ítem o no te pertenece.');
            }
        } else {
            $match = $this->findShoppingItemByName($data['name']);
            if ($match instanceof Response) {
                return $match;
            }
            $item = $match;
        }

        if ($item) {
            $updates = ['next_purchase' => true];
            if (isset($data['quantity'])) {
                $updates['to_buy'] = $data['quantity'];
            }
            if (isset($data['category_id'])) {
                $updates['category_id'] = $data['category_id'];
            }
            if ($baseUnit && ! $item->base_unit) {
                $updates['base_unit'] = $baseUnit;
            }
            $item = app(InventoryLedger::class)->saveProduct((int) auth()->id(), $updates, $item->id, context: ['channel' => 'mcp']);
        } else {
            if (! $baseUnit) {
                $similar = CatalogNames::similar($data['name'], Auth::user()->shoppingItems()->pluck('name'));

                return Response::error('Para crear un producto nuevo indica base_unit (g, ml o unit).'
                    .($similar->isNotEmpty() ? ' ¿Quizás es uno de estos? '.$similar->implode(', ') : ''));
            }

            $item = app(InventoryLedger::class)->saveProduct((int) Auth::id(), [
                'name' => trim($data['name']),
                'base_unit' => $baseUnit,
                'stock' => 0,
                'to_buy' => $data['quantity'] ?? 1,
                'category_id' => $data['category_id'] ?? null,
                'status' => 'available',
                'next_purchase' => true,
            ]);
            $created = true;
        }

        $recorded = $this->recordVariantPrice($item, $data, $store);
        $verb = $created ? 'creado y añadido' : 'añadido';

        return Response::text("Ítem \"{$item->name}\" {$verb} a la lista de compras (id: {$item->id})".($recorded ? "; {$recorded}" : '').'.');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'item_id' => $schema->string()
                ->description('Identificador (UUID) de un ítem ya existente en el catálogo. Alternativa a "name".'),
            'name' => $schema->string()
                ->description('Nombre genérico del producto, en singular y sin marca ni tamaño. Alternativa a "item_id". Si ya existe, se reutiliza.'),
            'quantity' => $schema->number()
                ->description('Cantidad de paquetes a comprar (admite decimales).'),
            'category_id' => $schema->string()
                ->description('Identificador de categoría del usuario. Consulta category_options en ListShoppingItemsTool.'),
            ...$this->variantPriceSchema($schema),
        ];
    }
}
