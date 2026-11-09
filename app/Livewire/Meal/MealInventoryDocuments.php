<?php

namespace App\Livewire\Meal;

use App\Livewire\Concerns\WithManagementCard;
use App\Models\ShoppingItem;
use App\Services\Meal\InventoryHistory;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Movimientos de inventario')]
class MealInventoryDocuments extends Component
{
    use WithManagementCard;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public string $type = '';

    #[Url]
    public string $channel = '';

    #[Url]
    public string $itemId = '';

    public function mount(): void
    {
        if (! request()->has('per_page')) {
            $this->perPage = 50;
        }
    }

    public function updated(string $property): void
    {
        if (in_array($property, [...$this->filterProperties(), 'search'])) {
            $this->resetPage();
            $this->resetValidation();
        }
    }

    protected function filterProperties(): array
    {
        return ['from', 'to', 'type', 'channel', 'itemId'];
    }

    protected function filters(): array
    {
        return ['search' => $this->search, 'from' => $this->from, 'to' => $this->to, 'type' => $this->type,
            'channel' => $this->channel, 'item_id' => $this->itemId, 'page' => max(1, (int) $this->getPage()), 'per_page' => $this->perPage()];
    }

    public function state(): array
    {
        return array_filter(['q' => $this->search, 'from' => $this->from, 'to' => $this->to, 'type' => $this->type,
            'channel' => $this->channel, 'itemId' => $this->itemId, 'page' => $this->getPage(), 'per_page' => $this->perPage()], fn ($v) => $v !== '');
    }

    public function activeFilters(): array
    {
        $chips = [];
        foreach ($this->filterProperties() as $key) {
            if ($this->{$key} !== '') {
                $label = match ($key) {
                    'from' => 'Desde: '.$this->from, 'to' => 'Hasta: '.$this->to,
                    'type' => InventoryHistory::TYPES[$this->type] ?? $this->type,
                    'channel' => InventoryHistory::CHANNELS[$this->channel] ?? $this->channel, default => 'Ingrediente seleccionado',
                };
                $chips[] = ['key' => $key, 'value' => null, 'label' => $label, 'icon' => 'bi-funnel'];
            }
        }

        return $chips;
    }

    protected function paginator(array $data, array $pagination): LengthAwarePaginator
    {
        return new LengthAwarePaginator($data, $pagination['total'], $pagination['per_page'], $pagination['page'],
            ['path' => request()->url(), 'query' => request()->except('page')]);
    }

    protected function validationErrors(ValidationException $exception): void
    {
        foreach ($exception->errors() as $field => $messages) {
            $this->addError($field === 'item_id' ? 'itemId' : $field, $messages[0]);
        }
    }

    public function render()
    {
        $loadError = false;
        $products = collect();
        $result = ['documents' => [], 'pagination' => ['total' => 0, 'per_page' => $this->perPage(), 'page' => 1], 'total_documents' => 0];
        try {
            $products = ShoppingItem::where('user_id', auth()->id())->orderBy('name')->pluck('name', 'id');
            $result = app(InventoryHistory::class)->documents((int) auth()->id(), $this->filters());
        } catch (ValidationException $exception) {
            $this->validationErrors($exception);
        } catch (QueryException) {
            $loadError = true;
        }

        return view('livewire.meal.meal-inventory-documents', [
            'documents' => $this->paginator($result['documents'], $result['pagination']), 'totalDocuments' => $result['total_documents'], 'loadError' => $loadError,
            'products' => $products,
        ]);
    }
}
