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
        // CHG-136: el panel abre con una petición (`panel-shopping`); las listas solo hacen falta con el formulario abierto.
        $open = $this->showForm || $this->showPurchase;

        return view('livewire.home.panel.shopping-editor', [
            'places' => $open ? Store::orderBy('name')->pluck('name', 'id') : collect(),
            'catalogNames' => $open ? ShoppingItem::orderBy('name')->pluck('name') : collect(),
            'purchaseItem' => $this->showPurchase ? ShoppingItem::with('variants.brand')->find($this->purchaseItemId) : null,
        ]);
    }
}
