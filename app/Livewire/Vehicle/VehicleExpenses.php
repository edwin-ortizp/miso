<?php

namespace App\Livewire\Vehicle;

use App\Livewire\Concerns\WithManagementCard;
use App\Livewire\Vehicle\Concerns\FiltersByPeriod;
use App\Livewire\Vehicle\Concerns\InteractsWithVehicle;
use App\Livewire\Vehicle\Concerns\ManagesExpenseCategories;
use App\Livewire\Vehicle\Concerns\ManagesVehicleExpenses;
use App\Models\VehicleExpense;
use App\Models\VehicleExpenseCategory;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Otros gastos del vehículo: lo que no es combustible ni mantenimiento, con categorías editables.
 */
#[Layout('layouts.app')]
class VehicleExpenses extends Component
{
    use FiltersByPeriod;
    use InteractsWithVehicle;
    use ManagesExpenseCategories;
    use ManagesVehicleExpenses;
    use WithManagementCard;

    #[Url(as: 'q', history: true, except: '')]
    public string $expenseSearch = '';

    public const SORTS = [
        'recent' => 'Más reciente',
        'oldest' => 'Más antiguo',
        'amount' => 'Mayor monto',
    ];

    #[Url(as: 'xsort', history: true, except: 'recent')]
    public string $expenseSort = 'recent';

    public function updatedExpenseSort(): void
    {
        $this->expenseSort = array_key_exists($this->expenseSort, self::SORTS) ? $this->expenseSort : 'recent';
        $this->resetPage();
    }

    #[Url(as: 'period', history: true, except: '')]
    public string $expensePeriod = '';

    #[Url(as: 'category', history: true, except: '')]
    public string $expenseCategory = '';

    public function mount(string $vehicle): void
    {
        $this->initializeVehicle($vehicle);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['expenseSearch', 'expensePeriod', 'expenseCategory'], true)) {
            $this->resetPage();
        }
    }

    public function clearExpenseFilters(): void
    {
        $this->reset('expenseSearch', 'expensePeriod', 'expenseCategory');
        $this->resetPage();
    }

    public function render()
    {
        $vehicle = $this->vehicle();
        $categoryOptions = VehicleExpenseCategory::hierarchyOptions(auth()->id());
        $categoryOrder = array_flip(array_keys($categoryOptions));
        $categories = VehicleExpenseCategory::query()->with('parent')->withCount('expenses')->orderBy('sort_order')->orderBy('name')->get()->sortBy(fn ($category) => $categoryOrder[$category->id] ?? PHP_INT_MAX);
        $totalCount = VehicleExpense::query()->where('vehicle_id', $vehicle->id)->count();
        $expenses = $this->filteredExpenses($vehicle->id)
            ->with('category.parent')
            ->when(in_array($this->expenseSort, ['amount'], true), fn (Builder $query) => $query->orderByDesc($this->expenseSort))
            ->orderBy('spent_on', $this->expenseSort === 'oldest' ? 'asc' : 'desc')->orderBy('created_at', $this->expenseSort === 'oldest' ? 'asc' : 'desc')
            ->paginate($this->perPage());
        $filteredAmount = (float) $this->filteredExpenses($vehicle->id)->sum('amount');
        $top = $this->filteredExpenses($vehicle->id)
            ->selectRaw('vehicle_expense_category_id, SUM(amount) as total')
            ->groupBy('vehicle_expense_category_id')->orderByDesc('total')->first();
        $topCategory = $top ? ['name' => $categoryOptions[$top->vehicle_expense_category_id] ?? '—', 'amount' => (float) $top->total] : null;
        $deletingCategory = $this->deletingCategoryId ? $categories->firstWhere('id', $this->deletingCategoryId) : null;
        $periodOptions = static::periodOptions();
        $energyUi = $this->energyUi($vehicle);

        $sorts = self::SORTS;

        return view('livewire.vehicle.vehicle-expenses', compact('sorts', 
            'vehicle', 'categories', 'categoryOptions', 'totalCount', 'expenses', 'filteredAmount', 'topCategory', 'deletingCategory', 'periodOptions', 'energyUi',
        ));
    }

    private function filteredExpenses(string $vehicleId): Builder
    {
        $term = trim($this->expenseSearch);

        return $this->applyPeriod(VehicleExpense::query()->where('vehicle_id', $vehicleId), 'spent_on', $this->expensePeriod)
            ->when($this->expenseCategory !== '', fn (Builder $query) => $query->whereIn('vehicle_expense_category_id', \App\Models\VehicleExpenseCategory::expandSelection([$this->expenseCategory], auth()->id(), 'id')))
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $match) => $match
                ->where('description', 'like', "%{$term}%")->orWhere('provider', 'like', "%{$term}%")));
    }
}
