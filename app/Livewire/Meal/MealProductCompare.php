<?php

namespace App\Livewire\Meal;

use App\Models\ShoppingItem;
use App\Models\ShoppingItemPrice;
use App\Services\Meal\UnitConverter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Comparar precios')]
class MealProductCompare extends Component
{
    public ShoppingItem $item;

    #[Url(as: 'store', history: true)]
    public string $storeFilter = '';

    #[Url(as: 'brand', history: true)]
    public string $brandFilter = '';

    public bool $showForm = false;

    public string $priceVariantId = '';

    public string $priceStoreId = '';

    public $priceAmount = null;

    public string $priceDate = '';

    public string $priceSource = 'ticket';

    public bool $pricePaid = true;

    public function mount(ShoppingItem $item): void
    {
        $this->item = $item;
    }

    public function openForm(?string $variantId = null): void
    {
        $this->resetValidation();
        $this->priceVariantId = $variantId ?? (string) $this->item->variants()->value('id');
        $this->priceStoreId = '';
        $this->priceAmount = null;
        $this->priceDate = now()->toDateString();
        $this->priceSource = 'ticket';
        $this->pricePaid = true;
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
    }

    /** Registra un precio observado o pagado. Un precio de ticket queda verificado. */
    public function savePrice(): void
    {
        $data = $this->validate([
            'priceVariantId' => ['required', Rule::in($this->item->variants()->pluck('id'))],
            'priceStoreId' => ['required', Rule::in(\App\Models\Store::pluck('id'))],
            'priceAmount' => 'required|numeric|min:0',
            'priceDate' => 'required|date',
            'priceSource' => ['required', Rule::in(array_keys(ShoppingItemPrice::SOURCES))],
        ], attributes: ['priceVariantId' => 'variante', 'priceStoreId' => 'tienda', 'priceAmount' => 'precio', 'priceDate' => 'fecha']);

        $verified = $data['priceSource'] === 'ticket';
        ShoppingItemPrice::create([
            'shopping_item_variant_id' => $data['priceVariantId'],
            'store_id' => $data['priceStoreId'],
            'amount' => $data['priceAmount'],
            'observed_on' => $data['priceDate'],
            'source' => $data['priceSource'],
            'paid' => $this->pricePaid,
            'verified_at' => $verified ? now() : null,
            'verified_by' => $verified ? auth()->id() : null,
        ]);

        $this->showForm = false;
    }

    #[On('stores-updated')]
    public function refreshStores(): void
    {
        // El evento basta para volver a renderizar con el catálogo de tiendas actualizado.
    }

    public function verifyPrice(string $priceId): void
    {
        $this->item->prices()->whereKey($priceId)->first()?->update(['verified_at' => now(), 'verified_by' => auth()->id()]);
    }

    public function removeFilter(string $key): void
    {
        if (in_array($key, ['storeFilter', 'brandFilter'], true)) {
            $this->reset($key);
        }
    }

    public function clearFilters(): void
    {
        $this->reset('storeFilter', 'brandFilter');
    }

    public function render()
    {
        $item = ShoppingItem::withOffers()->findOrFail($this->item->id);
        $baseUnit = $item->base_unit;

        $offers = $item->offers()
            ->when($this->storeFilter, fn ($offers) => $offers->filter(fn ($offer) => $offer['price']->store_id === $this->storeFilter))
            ->when($this->brandFilter, fn ($offers) => $offers->filter(fn ($offer) => $offer['variant']->brand_id === $this->brandFilter));

        $comparable = $offers->filter(fn ($offer) => $offer['per_base'] !== null)->sortBy('per_base')->values();
        $cheapest = $comparable->first();
        $comparable = $comparable->map(fn ($offer) => $offer + [
            'difference' => $cheapest && $cheapest['per_base'] > 0 ? ($offer['per_base'] / $cheapest['per_base'] - 1) * 100 : 0,
        ]);

        $pending = $item->variants->reject->isComparable();
        $withoutPrices = $item->variants->filter(fn ($variant) => $variant->isComparable() && $variant->prices->isEmpty());

        $history = $item->variants
            ->flatMap(fn ($variant) => $variant->prices->map(fn ($price) => ['variant' => $variant, 'price' => $price]))
            ->sortByDesc(fn ($row) => $row['price']->observed_on->format('Ymd').$row['price']->created_at?->format('YmdHis'))
            ->values();

        return view('livewire.meal.meal-product-compare', [
            'product' => $item,
            'comparable' => $comparable,
            'pending' => $pending,
            'withoutPrices' => $withoutPrices,
            'history' => $history,
            'comparisonLabel' => $baseUnit ? UnitConverter::comparisonLabel($baseUnit) : null,
            'stores' => $item->variants->flatMap->prices->pluck('store')->filter()->unique('id')->sortBy('name')->pluck('name', 'id'),
            'brands' => $item->variants->pluck('brand')->filter()->unique('id')->sortBy('name')->pluck('name', 'id'),
            'variantOptions' => $item->variants->mapWithKeys(fn ($variant) => [$variant->id => $variant->label($baseUnit)]),
            'storeOptions' => \App\Models\Store::orderBy('name')->pluck('name', 'id'),
        ]);
    }
}
