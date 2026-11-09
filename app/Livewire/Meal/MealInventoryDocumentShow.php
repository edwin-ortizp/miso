<?php

namespace App\Livewire\Meal;

use App\Services\Meal\InventoryHistory;
use Illuminate\Database\QueryException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

#[Title('Documento de inventario')]
class MealInventoryDocumentShow extends MealInventoryDocuments
{
    #[Locked]
    public string $operationId = '';

    #[Url(as: 'return')]
    public array $returnTo = [];

    public function mount(?string $operation = null): void
    {
        $this->operationId = (string) $operation;
        if (! request()->has('per_page')) {
            $this->perPage = 100;
        }
    }

    public function render()
    {
        $history = app(InventoryHistory::class);
        $loadError = false;
        $result = ['document' => null, 'lines' => [], 'corrections' => [], 'pagination' => ['total' => 0, 'per_page' => $this->perPage(), 'page' => 1]];
        try {
            $result = $history->document((int) auth()->id(), $this->operationId, ['page' => max(1, (int) $this->getPage()), 'per_page' => $this->perPage()]);
        } catch (QueryException) {
            $loadError = true;
        }

        return view('livewire.meal.meal-inventory-document-show', $result + [
            'linesPage' => $this->paginator($result['lines'], $result['pagination']), 'loadError' => $loadError,
            'backUrl' => $history->returnUrl((int) auth()->id(), $this->returnTo),
            'kardexReturn' => ['screen' => 'document', 'id' => $this->operationId, 'parent' => ['screen' => ($this->returnTo['screen'] ?? '') === 'ingredients' ? 'ingredients' : 'documents', 'filters' => $history->safeQuery(is_array($this->returnTo['filters'] ?? null) ? $this->returnTo['filters'] : [])]],
        ]);
    }
}
