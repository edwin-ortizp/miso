<?php

namespace App\Mcp\Tools\Shopping;

use App\Mcp\Tools\Shopping\Concerns\InteractsWithInventory;
use App\Models\MealCategory;
use App\Models\StorageLocation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Lista productos del usuario con stock, ubicación y precios, paginados. Por defecto solo pendientes de compra; para inventario usa only_pending=false y recorre next_page. Úsala cuando hable de mercado, compras o antes de agregar un ítem, para no duplicarlo.')]
class ListShoppingItemsTool extends Tool
{
    use InteractsWithInventory;

    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'name' => ['nullable', 'string'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'in_stock' => ['sometimes', 'boolean'],
            'storage_location_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('storage_locations', 'id')->where('user_id', auth()->id())],
            'only_pending' => ['nullable', 'boolean'],
            'not_purchased_days' => ['nullable', 'integer', 'min:1', 'max:36500'],
        ]);

        $query = Auth::user()->shoppingItems()->withOffers()->with(['purchaseLines.purchase.store', 'storageLocation.parent']);
        if (! empty($data['not_purchased_days'])) {
            $query->whereDoesntHave('purchaseLines.purchase', fn ($q) => $q->whereBetween('purchased_at', [now()->subDays($data['not_purchased_days']), now()]));
        }

        if (($data['only_pending'] ?? true) !== false) {
            $query->where('next_purchase', true);
        }

        if (! empty($data['name'])) {
            $name = $data['name'];
            $query->where(fn ($q) => $q->where('name', 'like', "%{$name}%")->orWhereHas('aliases', fn ($a) => $a->where('alias', 'like', "%{$name}%")));
        }

        if (array_key_exists('storage_location_id', $data)) {
            if ($data['storage_location_id'] === null) {
                $query->whereNull('storage_location_id');
            } else {
                $query->whereIn('storage_location_id', StorageLocation::expandSelection([$data['storage_location_id']], (int) auth()->id()));
            }
        }
        if ($data['in_stock'] ?? false) {
            $query->where('stock', '>', 0);
        }
        $items = $query->orderBy('name')->orderBy('id')->paginate(100, ['*'], 'page', $data['page'] ?? 1);

        return Response::structured([
            'pagination' => ['page' => $items->currentPage(), 'per_page' => $items->perPage(), 'total' => $items->total(), 'next_page' => $items->hasMorePages() ? $items->currentPage() + 1 : null],
            'category_options' => MealCategory::options(),
            'items' => $items->map(fn ($item) => [
                ...$this->inventorySummary($item),
                'quantity' => $item->to_buy,
                'min_stock' => $item->min_stock,
                'category_id' => $item->category_id,
                'purchase_history' => $item->purchaseHistorySummary(),
                'estimated_price' => $item->estimatedPrice(),
                'variants' => $item->variants->map(fn ($variant) => [
                    'variant_id' => $variant->id,
                    'label' => $variant->label($item->base_unit),
                    'preferred' => $variant->is_preferred,
                    'pending' => ! $variant->isComparable(),
                ])->all(),
                'offers' => $item->offers()->map(fn ($offer) => [
                    'variant_id' => $offer['variant']->id,
                    'price_id' => $offer['price']->id,
                    'variant' => $offer['variant']->label($item->base_unit),
                    'store' => $offer['price']->store?->name,
                    'price' => (float) $offer['price']->amount,
                    'date' => $offer['price']->observed_on->toDateString(),
                    'source' => $offer['price']->source,
                    'verified' => $offer['price']->isVerified(),
                    'price_per_base' => $offer['per_base'] !== null ? round($offer['per_base'], 2) : null,
                ])->all(),
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'page' => $schema->integer()->description('Página desde 1, 100 productos por página. Recorre next_page hasta null.'),
            'in_stock' => $schema->boolean()->description('true filtra stock mayor que cero; false u omitido no filtra.'),
            'storage_location_id' => $schema->string()->nullable()->description('UUID de ubicación, incluye sus hijas. null filtra sin ubicación; omitir no filtra.'),
            'not_purchased_days' => $schema->integer()->description('Sin compras en los últimos N días. Incluye nunca comprados, distinguidos en purchase_history.'),
            'name' => $schema->string()
                ->description('Filtra por nombre o alias del ítem.'),
            'only_pending' => $schema->boolean()
                ->description('Si es true (por defecto), solo muestra los pendientes de compra. Si es false, consulta todo el catálogo paginado. Para inventario usa false y, para existencias positivas, in_stock=true.'),
        ];
    }
}
