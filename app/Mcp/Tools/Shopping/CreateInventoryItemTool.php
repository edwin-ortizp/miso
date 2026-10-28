<?php

namespace App\Mcp\Tools\Shopping;

use App\Mcp\Tools\Shopping\Concerns\InteractsWithInventory;
use App\Mcp\Tools\Shopping\Concerns\ResolvesShoppingItem;
use App\Models\ShoppingItem;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Crea un producto nuevo de inventario con stock total contado y ubicación opcional, sin añadirlo a compras. Busca primero en el catálogo; si ya existe usa update-shopping-item-tool. Stock en g, ml o unit, sin conversiones implícitas de paquetes.')]
class CreateInventoryItemTool extends Tool
{
    use InteractsWithInventory, ResolvesShoppingItem;

    public function handle(Request $request): Response|ResponseFactory
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'base_unit' => ['required', Rule::in(array_keys(ShoppingItem::BASE_UNITS))],
            'stock' => ['required', 'numeric', 'min:0'],
            'storage_location_id' => $this->storageLocationRules(),
        ]);
        $data['name'] = trim($data['name']);
        if ($data['name'] === '') {
            return Response::error('Escribe un nombre válido.');
        }
        $match = $this->findShoppingItemByName($data['name']);
        if ($match instanceof Response) {
            return $match;
        }
        if ($match) {
            return Response::error("Ya existe {$match->name} (id: {$match->id}). Usa update-shopping-item-tool para actualizarlo.");
        }
        $item = Auth::user()->shoppingItems()->create([
            ...$data, 'to_buy' => 0, 'next_purchase' => false, 'status' => 'available',
        ]);

        return Response::structured(['item' => $this->inventorySummary($item)]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Nombre genérico, sin marca ni presentación.')->required(),
            'base_unit' => $schema->string()->enum(array_keys(ShoppingItem::BASE_UNITS))->required(),
            'stock' => $schema->number()->description('Total contado en la unidad base, admite cero y decimales.')->required(),
            'storage_location_id' => $schema->string()->nullable()->description('UUID de ubicación activa propia; null para ninguna. Consulta list-storage-locations-tool.'),
        ];
    }
}
