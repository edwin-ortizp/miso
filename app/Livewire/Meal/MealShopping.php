<?php

namespace App\Livewire\Meal;

use App\Livewire\Concerns\WithMealCategories;

use App\Livewire\Concerns\WithManagementCard;
use App\Models\MealPlanEntryItem;
use App\Models\ShoppingItem;
use App\Models\ShoppingItemPrice;
use App\Models\Store;
use App\Services\Meal\PurchaseRecorder;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Compras')]
class MealShopping extends Component
{
    use WithManagementCard, WithMealCategories;

    #[Url(as: 'q', history: true, keep: true)]
    public string $search = '';

    // Id de la tienda: el total y los subtotales usan sus precios.
    #[Url(as: 'place', history: true, keep: true)]
    public string $placeFilter = '';

    #[Url(as: 'group', history: true, keep: true)]
    public string $groupBy = 'category'; // 'category' or 'place'

    #[Url(as: 'view', history: true, keep: true)]
    public string $viewMode = 'compact'; // 'compact' or 'grouped'

    public bool $showForm = false;

    public string $itemName = '';

    public $itemQuantity = 1;

    public string $itemBaseUnit = '';

    public string $itemCategory = '';

    public string $itemStoreId = '';

    public $itemPrice = null;

    // Diálogo "Marcar como comprado"
    public bool $showPurchase = false;

    public ?string $purchaseItemId = null;

    public string $purchaseVariantId = '';

    public string $purchaseStoreId = '';

    public $purchaseQuantity = 1;

    public $purchaseAmount = null;

    #[Locked]
    public string $purchaseOperationKey = '';

    #[On('purchase-saved')]
    public function refreshPurchases(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPlaceFilter(): void
    {
        $this->resetPage();
    }

    public function mount(): void
    {
        $this->normalizeViewOptions();
    }

    public function openForm(): void
    {
        $this->reset(['itemName', 'itemQuantity', 'itemBaseUnit', 'itemCategory', 'itemStoreId', 'itemPrice']);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetValidation();
    }

    /**
     * Agrega un ítem a la lista: reutiliza el del catálogo con el mismo nombre o lo crea.
     */
    public function save(): void
    {
        $name = trim($this->itemName);
        $existing = $name !== '' ? ShoppingItem::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first() : null;

        $data = $this->validate([
            'itemName' => ['required', 'string', 'max:255'],
            'itemQuantity' => ['nullable', 'numeric', 'gt:0'],
            'itemBaseUnit' => [$existing?->base_unit ? 'nullable' : 'required', Rule::in(array_keys(ShoppingItem::BASE_UNITS))],
            'itemCategory' => ['nullable', 'string', 'in:'.implode(',', array_keys($this->categoryOptions))],
            'itemStoreId' => ['nullable', 'required_with:itemPrice', Rule::in(Store::pluck('id'))],
            'itemPrice' => ['nullable', 'numeric', 'min:0'],
        ], attributes: ['itemBaseUnit' => 'unidad base', 'itemStoreId' => 'tienda']);

        $attributes = array_filter([
            'to_buy' => $data['itemQuantity'],
            'category_id' => $data['itemCategory'] ?: null,
        ], fn ($value) => $value !== null);

        if ($existing) {
            $existing->update(['next_purchase' => true, ...$attributes] + ($existing->base_unit ? [] : ['base_unit' => $data['itemBaseUnit']]));
            $item = $existing;
        } else {
            $item = ShoppingItem::create([
                'name' => $name,
                'base_unit' => $data['itemBaseUnit'],
                'stock' => 0,
                'to_buy' => 1,
                'status' => 'available',
                ...$attributes,
                'next_purchase' => true,
            ]);
        }

        if ($data['itemStoreId'] && $data['itemPrice'] !== null && $data['itemPrice'] !== '') {
            // Sin marca ni presentación conocidas, el precio va a la variante preferida o a una genérica pendiente de completar.
            $variant = $item->variants()->orderByDesc('is_preferred')->first() ?? $item->variants()->create([]);
            $variant->prices()->create([
                'store_id' => $data['itemStoreId'],
                'amount' => $data['itemPrice'],
                'observed_on' => now()->toDateString(),
                'source' => 'manual',
            ]);
        }

        $this->showForm = false;
    }

    public function setViewMode(string $mode): void
    {
        $this->viewMode = $mode;
        $this->normalizeViewOptions();
    }

    public function toggleGroupBy()
    {
        $this->groupBy = $this->groupBy === 'category' ? 'place' : 'category';
    }

    public function toggleNextPurchase(string $id)
    {
        $item = ShoppingItem::find($id);
        if ($item) {
            $item->update(['next_purchase' => ! $item->next_purchase]);
        }
    }

    public function openPurchase(string $id): void
    {
        $item = ShoppingItem::withOffers()->find($id);
        if (! $item) {
            return;
        }

        $offer = $item->bestOffer($this->placeFilter ?: null);
        $this->resetValidation();
        $this->purchaseItemId = $item->id;
        $this->purchaseOperationKey = (string) Str::uuid();
        $this->purchaseVariantId = (string) ($offer['variant']->id ?? $item->variants->first()?->id ?? '');
        $this->purchaseStoreId = (string) ($offer['price']->store_id ?? '');
        $this->purchaseQuantity = max((float) $item->to_buy, 1);
        $this->purchaseAmount = $offer ? (float) $offer['price']->amount : null;
        $this->showPurchase = true;
    }

    public function closePurchase(): void
    {
        $this->showPurchase = false;
    }

    /**
     * Marca como comprado: suma al stock el contenido comprado y, si se indica el precio pagado,
     * lo guarda como precio de ticket verificado para el historial.
     */
    public function confirmPurchase(): void
    {
        $item = ShoppingItem::with('variants')->find($this->purchaseItemId);
        if (! $item) {
            return;
        }

        $data = $this->validate([
            'purchaseVariantId' => ['nullable', Rule::in($item->variants->pluck('id'))],
            'purchaseQuantity' => ['required', 'numeric', 'gt:0'],
            'purchaseAmount' => ['nullable', 'numeric', 'min:0'],
            'purchaseStoreId' => ['nullable', 'required_with:purchaseAmount', Rule::in(Store::pluck('id'))],
        ], attributes: ['purchaseQuantity' => 'cantidad', 'purchaseAmount' => 'precio pagado', 'purchaseStoreId' => 'tienda']);

        $amount = $data['purchaseAmount'] ?? null;
        app(PurchaseRecorder::class)->record(
            $item,
            $item->variants->firstWhere('id', $data['purchaseVariantId']),
            (float) $data['purchaseQuantity'],
            $amount === null || $amount === '' ? null : (float) $amount,
            Store::find($data['purchaseStoreId'] ?? null),
            $this->purchaseOperationKey,
        );

        $this->showPurchase = false;
    }

    public function addSuggested(string $id, $quantity = null): void
    {
        $item = ShoppingItem::find($id);
        $item?->update(['next_purchase' => true] + ($quantity ? ['to_buy' => max((float) $item->to_buy, (float) $quantity)] : []));
    }

    #[On('ingredients-imported')]
    #[On('stores-updated')]
    public function refreshIngredients(): void
    {
        // The event is enough to trigger a fresh render of the shopping list.
    }

    private function normalizeViewOptions(): void
    {
        if (! in_array($this->viewMode, ['compact', 'grouped'], true)) {
            $this->viewMode = 'compact';
        }

        if (! in_array($this->groupBy, ['category', 'place'], true)) {
            $this->groupBy = 'category';
        }
    }

    /**
     * Lo que piden las comidas planeadas de la semana, en unidad base, descontando lo que hay en casa.
     */
    private function weeklyNeeds(): Collection
    {
        $preview = app(\App\Services\Meal\MealNeeds::class)->preview((int) auth()->id(), today()->startOfWeek()->toDateString(), today()->endOfWeek()->toDateString());
        $products = ShoppingItem::where('user_id', auth()->id())->withOffers()->whereIn('id', collect($preview['needs'])->pluck('shopping_item_id')->filter())->get()->keyBy('id');

        return collect($preview['needs'])->filter(fn ($row) => $products->has($row['shopping_item_id']))
            ->map(fn ($row) => $row + ['shopping_item' => $products[$row['shopping_item_id']], 'recipes' => collect()]);
    }

    protected function filterProperties(): array
    {
        return ['placeFilter'];
    }

    public function render()
    {
        $query = ShoppingItem::query()
            ->withOffers()
            ->where('next_purchase', true)
            ->when($this->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when($this->placeFilter, fn ($q, $storeId) => $q->whereHas('prices', fn ($pq) => $pq->where('store_id', $storeId)))
            ->orderBy('name');

        // El total estimado se calcula sobre toda la lista, no solo la página visible.
        $storeId = $this->placeFilter ?: null;
        $allItems = (clone $query)->get();
        $estimatedTotal = $allItems->sum(fn ($item) => $item->estimatedSubtotal($storeId) ?? 0);
        $unpricedCount = $allItems->filter(fn ($item) => $item->estimatedPrice($storeId) === null)->count();

        // Paginación de la Card de gestión: se agrupa la página visible.
        $items = $query->paginate($this->perPage());
        $pageItems = $items->getCollection();

        if ($this->groupBy === 'place') {
            // Cada artículo va a la tienda donde está su mejor oferta.
            $grouped = $pageItems
                ->groupBy(fn ($item) => $item->bestOffer($storeId)['price']->store?->name ?? '')
                ->sortKeys();
        } else {
            $grouped = $pageItems->groupBy('category_id')->sortKeys();
        }

        $neededItems = $this->weeklyNeeds();
        $neededItemIds = $neededItems->pluck('shopping_item_id')->unique();

        $belowMinimum = ShoppingItem::query()
            ->where('next_purchase', false)
            ->whereNotNull('min_stock')
            ->whereColumn('stock', '<', 'min_stock')
            ->orderBy('name')
            ->get();

        $purchaseItem = $this->showPurchase ? ShoppingItem::with('variants.brand')->find($this->purchaseItemId) : null;

        return view('livewire.meal.meal-shopping', [
            'items' => $items,
            'grouped' => $grouped,
            'neededItems' => $neededItems,
            'neededItemIds' => $neededItemIds,
            'neededCost' => $neededItems->sum(fn ($needed) => $needed['cost'] ?? 0),
            'belowMinimum' => $belowMinimum,
            'places' => Store::orderBy('name')->pluck('name', 'id'),
            'totalItems' => $items->total(),
            'neededCount' => $neededItemIds->count(),
            'estimatedTotal' => $estimatedTotal,
            'unpricedCount' => $unpricedCount,
            'catalogNames' => ShoppingItem::orderBy('name')->pluck('name'),
            'purchaseItem' => $purchaseItem,
            'sources' => ShoppingItemPrice::SOURCES,
        ]);
    }
}
