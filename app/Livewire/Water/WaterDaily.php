<?php

namespace App\Livewire\Water;

use App\Actions\LogDrink;
use App\Livewire\Concerns\HasUrlDate;
use App\Models\DrinkLog;
use App\Models\DrinkType;
use App\Support\WaterGoal;
use App\Support\WaterProgress;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;
use App\Livewire\Concerns\WithDefaultDateFilter;
use App\Livewire\Concerns\WithManagementCard;
use Livewire\Attributes\Url;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Hidratación')]
class WaterDaily extends Component
{
    use HasUrlDate;
    use WithManagementCard;
    use WithDefaultDateFilter;

    public const SORTS = [
        'recent' => 'Más reciente',
        'oldest' => 'Más antiguo',
        'amount' => 'Mayor cantidad',
        'hydration' => 'Mayor hidratación',
    ];

    public int $dailyGoal = 2500;

    #[Url(as: 'sort', history: true, except: 'recent')]
    public string $sort = 'recent';

    #[Url(as: 'q', history: true, except: '')]
    public string $search = '';

    #[Url(as: 'drink', history: true, except: '')]
    public string $drinkFilter = '';

    #[Url(as: 'month', history: true, except: '')]
    public string $calendarMonth = '';

    // Form fields
    public string $drinkTypeId = '';
    public int $amount = 250;
    public string $time = '';
    public bool $showForm = false;
    public ?string $editingId = null;

    // Drink catalog fields
    public bool $showCatalog = false;
    public bool $showDrinkTypeForm = false;
    public ?string $editingDrinkTypeId = null;
    public string $catalogDrinkName = '';
    public string $catalogDrinkIcon = '💧';
    public string $catalogHydrationFactor = '1.00';
    public string $catalogMessage = '';

    public function mount()
    {
        $this->initializeSelectedDate();
        $this->setDateScope($this->dateScope);
        $this->calendarMonth = $this->normalizeCalendarMonth($this->calendarMonth);
        $this->dailyGoal = WaterGoal::forUser(Auth::user());
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        $this->sort = array_key_exists($this->sort, self::SORTS) ? $this->sort : 'recent';
        $this->resetPage();
    }

    public function applyFilters(string $scope, string $drink): void
    {
        $this->setDateScope($scope);
        $this->drinkFilter = $drink !== '' && DrinkType::whereKey($drink)->exists() ? $drink : '';
        $this->resetPage();
    }

    public function removeFilter(string $key, ?string $value = null): void
    {
        $this->resetPage();
        match ($key) {
            'date' => $this->dateScope = 'all',
            'drink' => $this->drinkFilter = '',
            default => null,
        };
    }

    public function clearFilters(): void
    {
        $this->resetPage();
        $this->dateScope = 'all';
        $this->drinkFilter = '';
    }

    public function previousMonth(): void
    {
        $this->calendarMonth = Carbon::parse($this->calendarMonth.'-01')->subMonthNoOverflow()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->calendarMonth = Carbon::parse($this->calendarMonth.'-01')->addMonthNoOverflow()->format('Y-m');
    }

    private function normalizeCalendarMonth(string $month): string
    {
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1 ? $month : substr($this->selectedDate, 0, 7);
    }

    public function previousDay()
    {
        $this->selectedDate = Carbon::parse($this->selectedDate)->subDay()->toDateString();
        $this->calendarMonth = substr($this->selectedDate, 0, 7);
    }

    public function nextDay()
    {
        $this->selectedDate = Carbon::parse($this->selectedDate)->addDay()->toDateString();
        $this->calendarMonth = substr($this->selectedDate, 0, 7);
    }

    public function today()
    {
        $this->selectedDate = now()->toDateString();
        $this->calendarMonth = substr($this->selectedDate, 0, 7);
    }

    public function openForm(?string $id = null)
    {
        $this->resetErrorBag();
        if ($id) {
            $log = DrinkLog::find($id);
            if ($log) {
                $this->editingId = $id;
                $this->drinkTypeId = $log->drink_type_id ?? '';
                $this->amount = $log->amount;
                $this->time = $log->time;
            }
        } else {
            $this->editingId = null;
            $this->drinkTypeId = '';
            $this->amount = 250;
            $this->time = now()->format('H:i');
        }
        $this->showForm = true;
    }

    public function closeForm()
    {
        $this->resetErrorBag();
        $this->showForm = false;
        $this->editingId = null;
        $this->resetForm();
    }

    public function openCatalog(): void
    {
        $this->showCatalog = true;
        $this->catalogMessage = '';
    }

    public function closeCatalog(): void
    {
        $this->showCatalog = false;
        $this->showDrinkTypeForm = false;
        $this->resetDrinkTypeForm();
    }

    public function openDrinkTypeForm(?string $id = null): void
    {
        $this->resetDrinkTypeForm();

        if ($id && ($drinkType = DrinkType::find($id))) {
            $this->editingDrinkTypeId = $drinkType->id;
            $this->catalogDrinkName = $drinkType->name;
            $this->catalogDrinkIcon = $drinkType->icon ?: '💧';
            $this->catalogHydrationFactor = (string) $drinkType->hydration_factor;
        }

        $this->showDrinkTypeForm = true;
    }

    public function closeDrinkTypeForm(): void
    {
        $this->showDrinkTypeForm = false;
        $this->resetDrinkTypeForm();
    }

    public function saveDrinkType(): void
    {
        $this->catalogDrinkName = trim($this->catalogDrinkName);
        $this->catalogDrinkIcon = trim($this->catalogDrinkIcon);

        $drinkType = $this->editingDrinkTypeId
            ? DrinkType::find($this->editingDrinkTypeId)
            : new DrinkType();

        if (!$drinkType) {
            $this->catalogMessage = 'La bebida ya no está disponible.';
            $this->closeDrinkTypeForm();

            return;
        }

        $data = $this->validate([
            'catalogDrinkName' => [
                'required',
                'string',
                'max:255',
                Rule::unique('drink_types', 'name')
                    ->where(fn ($query) => $query->where('user_id', Auth::id()))
                    ->ignore($drinkType->id),
            ],
            'catalogDrinkIcon' => ['required', 'string', 'max:40'],
            'catalogHydrationFactor' => ['required', 'numeric', 'min:0', 'max:9.99'],
        ]);

        $drinkType->fill([
            'name' => $data['catalogDrinkName'],
            'icon' => $data['catalogDrinkIcon'],
            'hydration_factor' => $data['catalogHydrationFactor'],
        ])->save();

        $this->catalogMessage = $this->editingDrinkTypeId
            ? 'Bebida actualizada. Los registros históricos no se modificaron.'
            : 'Bebida creada.';
        $this->closeDrinkTypeForm();
    }

    public function deleteDrinkType(string $id): void
    {
        $drinkType = DrinkType::find($id);

        if (!$drinkType) {
            $this->catalogMessage = 'La bebida ya no está disponible.';

            return;
        }

        if (DrinkLog::where('drink_type_id', $drinkType->id)->exists()) {
            $this->catalogMessage = 'No se puede eliminar una bebida con registros históricos.';

            return;
        }

        $drinkType->delete();
        $this->catalogMessage = 'Bebida eliminada.';
    }

    public function quickAdd(string $drinkTypeId, int $amount)
    {
        $drinkType = DrinkType::find($drinkTypeId);
        if (!$drinkType) return;

        LogDrink::handle($drinkType, $this->selectedDate, $amount);
    }

    public function save()
    {
        $this->validate([
            'drinkTypeId' => ['required', 'string'],
            'amount' => ['required', 'integer', 'min:1'],
            'time' => ['required', 'date_format:H:i'],
        ]);

        $drinkType = DrinkType::find($this->drinkTypeId);
        if (!$drinkType || $this->amount <= 0) return;

        if ($this->editingId) {
            $log = DrinkLog::find($this->editingId);
            if ($log) {
                // Editar no mueve el registro de día; solo cambia la hora dentro del suyo.
                LogDrink::update($log, $drinkType, $this->amount, $this->time);
            }
        } else {
            LogDrink::handle($drinkType, $this->selectedDate, $this->amount, $this->time);
        }

        $this->closeForm();
    }

    public function delete(string $id)
    {
        DrinkLog::where('id', $id)->delete();
    }

    private function resetForm()
    {
        $this->drinkTypeId = '';
        $this->amount = 250;
        $this->time = '';
    }

    private function resetDrinkTypeForm(): void
    {
        $this->resetErrorBag();
        $this->editingDrinkTypeId = null;
        $this->catalogDrinkName = '';
        $this->catalogDrinkIcon = '💧';
        $this->catalogHydrationFactor = '1.00';
    }

    public function render()
    {
        $dayLogs = DrinkLog::whereDate('date', $this->selectedDate);
        $search = trim($this->search);
        $logs = $this->applyDateScope(DrinkLog::query())
            ->when($search !== '', fn ($q) => $q->where('drink_type', 'like', '%'.$search.'%'))
            ->when($this->drinkFilter !== '', fn ($q) => $q->where('drink_type_id', $this->drinkFilter))
            ->when(in_array($this->sort, ['amount', 'hydration'], true),
                fn ($q) => $q->orderByDesc($this->sort === 'amount' ? 'amount' : 'hydration_value'))
            ->orderBy('date', $this->sort === 'oldest' ? 'asc' : 'desc')
            ->orderBy('timestamp', $this->sort === 'oldest' ? 'asc' : 'desc')
            ->paginate($this->perPage());

        // El progreso del día se calcula sobre todos los registros, no solo la página visible.
        $totalHydration = (clone $dayLogs)->sum('hydration_value');
        $totalAmount = (clone $dayLogs)->sum('amount');
        $drinkTypes = DrinkType::orderBy('name')->get();
        $drinkFilterLabel = $this->drinkFilter !== '' ? $drinkTypes->firstWhere('id', $this->drinkFilter)?->name : null;
        $activeFilters = $this->dateFilterChips();
        if ($drinkFilterLabel) {
            $activeFilters[] = ['key' => 'drink', 'value' => null, 'label' => 'Bebida: '.$drinkFilterLabel, 'icon' => 'bi-cup-straw'];
        }
        // Sin tope: el usuario puede superar su meta; la barra se limita en la vista.
        $rawPercentage = $this->dailyGoal > 0 ? (int) round(($totalHydration / $this->dailyGoal) * 100) : 0;

        return view('livewire.water.water-daily', [
            'logs' => $logs,
            'sorts' => self::SORTS,
            'totalLogs' => DrinkLog::count(),
            'activeFilters' => $activeFilters,
            'dateScopes' => $this->dateScopeOptions(),
            'totalHydration' => $totalHydration,
            'totalAmount' => $totalAmount,
            'drinkTypes' => $drinkTypes,
            'rawPercentage' => $rawPercentage,
            'streak' => WaterProgress::streak($this->dailyGoal),
            'monthData' => WaterProgress::month(Carbon::parse($this->selectedDate), $this->dailyGoal, Carbon::parse($this->normalizeCalendarMonth($this->calendarMonth).'-01')),
        ]);
    }
}
