<?php

namespace App\Livewire\Meal;

use App\Services\Meal\InventoryHistory;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

#[Title('Kardex del ingrediente')]
class MealInventoryHistory extends MealInventoryDocuments
{
    #[Locked]
    public string $productId = '';

    #[Url(as: 'return')]
    public array $returnTo = [];

    public function mount(?string $item = null): void
    {
        parent::mount();
        $this->productId = (string) $item;
    }

    protected function filterProperties(): array
    {
        return ['from', 'to', 'type', 'channel'];
    }

    public function render()
    {
        $history = app(InventoryHistory::class);
        $loadError = false;
        $result = ['product' => null, 'coverage_started_at' => null,
            'movements' => [], 'total_movements' => 0, 'pagination' => ['total' => 0, 'per_page' => $this->perPage(), 'page' => 1]];
        try {
            $result['product'] = $history->product((int) auth()->id(), $this->productId);
            $result = $history->movements((int) auth()->id(), $this->productId, $this->filters());
        } catch (ValidationException $exception) {
            $this->validationErrors($exception);
        } catch (QueryException) {
            $loadError = true;
        }

        return view('livewire.meal.meal-inventory-history', $result + [
            'movementsPage' => $this->paginator($result['movements'], $result['pagination']), 'loadError' => $loadError,
            'backUrl' => $history->returnUrl((int) auth()->id(), $this->returnTo ?: ['screen' => 'ingredients']),
            'documentReturn' => ['screen' => 'documents', 'filters' => ['itemId' => $this->productId]],
        ]);
    }
}
