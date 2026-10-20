<?php

namespace App\Livewire\Meal;

use App\Livewire\Concerns\WithManagementCard;
use App\Models\Purchase;
use App\Models\ShoppingItem;
use App\Models\Store;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Historial de compras')]
class MealPurchases extends Component
{
    use WithManagementCard;

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public string $storeId = '';

    #[Url]
    public string $itemId = '';

    #[Url(as: 'q')]
    public string $search = '';

    public function updated($property): void
    {
        if (in_array($property, ['from', 'to', 'storeId', 'itemId', 'search'])) {
            $this->resetPage();
        }
    }

    #[On('purchase-saved')]
    public function refreshPurchases(): void
    {
        $this->resetPage();
    }

    protected function filterProperties(): array
    {
        return ['from', 'to', 'storeId', 'itemId'];
    }

    public function render()
    {
        $query = Purchase::where('user_id', auth()->id())->with('store')->withCount('lines');
        if (trim($this->search) !== '') {
            $search = '%'.trim($this->search).'%';
            $query->where(fn ($q) => $q->where('ticket_reference', 'like', $search)
                ->orWhereHas('store', fn ($store) => $store->where('user_id', auth()->id())->where('name', 'like', $search))
                ->orWhereHas('lines', fn ($line) => $line->where('product_name', 'like', $search)));
        }
        if ($this->from) {
            $query->whereDate('purchased_at', '>=', $this->from);
        }
        if ($this->to) {
            $query->whereDate('purchased_at', '<=', $this->to);
        }
        if ($this->storeId) {
            $query->where('store_id', $this->storeId);
        }
        if ($this->itemId) {
            $query->whereHas('lines', fn ($q) => $q->where('shopping_item_id', $this->itemId));
        }

        return view('livewire.meal.meal-purchases', [
            'purchases' => $query->orderByDesc('purchased_at')->orderByDesc('id')->paginate($this->perPage()),
            'stores' => Store::where('user_id', auth()->id())->orderBy('name')->pluck('name', 'id'),
            'products' => ShoppingItem::where('user_id', auth()->id())->orderBy('name')->pluck('name', 'id'),
            'totalPurchases' => Purchase::where('user_id', auth()->id())->count(),
            'historyState' => array_filter(['q' => $this->search, 'from' => $this->from, 'to' => $this->to, 'storeId' => $this->storeId, 'itemId' => $this->itemId, 'page' => $this->getPage(), 'per_page' => $this->perPage()], fn ($value) => $value !== ''),
        ]);
    }
}
