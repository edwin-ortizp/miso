<?php

namespace App\Livewire\Home\Panel;

use App\Livewire\Meal\MealShopping;
use App\Models\ShoppingItem;
use App\Models\Store;
use Livewire\Attributes\On;

class ShoppingEditor extends MealShopping
{
    public function boot(): void
    {
        abort_unless(auth()->check(), 403);
    }

    #[On('panel-shopping')]
    public function openPanelShopping(?string $id = null): void
    {
        $id ? $this->openPurchase(ShoppingItem::findOrFail($id)->id) : $this->openForm();
    }

    public function save(): void
    {
        parent::save();
        if (! $this->showForm) {
            $this->dispatch('panel-changed');
        }
    }

    public function confirmPurchase(): void
    {
        parent::confirmPurchase();
        if (! $this->showPurchase) {
            $this->dispatch('panel-changed');
        }
    }

    public function render()
    {
        return view('livewire.home.panel.shopping-editor', [
            'places' => Store::orderBy('name')->pluck('name', 'id'),
            'catalogNames' => ShoppingItem::orderBy('name')->pluck('name'),
            'purchaseItem' => $this->showPurchase ? ShoppingItem::with('variants.brand')->find($this->purchaseItemId) : null,
        ]);
    }
}
