<?php

namespace App\Livewire\Exercise;

use App\Actions\LogExercise;
use App\Livewire\Concerns\HasUrlDate;
use App\Models\ExerciseLog;
use App\Models\ExerciseType;
use App\Support\ExerciseProgress;
use App\Support\GoalCalendar;
use Carbon\Carbon;
use Livewire\Component;
use App\Livewire\Concerns\WithDefaultDateFilter;
use App\Livewire\Concerns\WithManagementCard;
use Livewire\Attributes\Url;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Ejercicio')]
class ExerciseDaily extends Component
{
    use HasUrlDate;
    use WithManagementCard;
    use WithDefaultDateFilter;

    public const SORTS = [
        'recent' => 'Más reciente',
        'oldest' => 'Más antiguo',
        'duration' => 'Mayor duración',
        'calories' => 'Más calorías',
    ];

    #[Url(as: 'sort', history: true, except: 'recent')]
    public string $sort = 'recent';

    #[Url(as: 'q', history: true, except: '')]
    public string $search = '';

    #[Url(as: 'type', history: true, except: '')]
    public string $typeFilter = '';

    #[Url(as: 'month', history: true, except: '')]
    public string $calendarMonth = '';

    public int $dailyGoal = ExerciseProgress::DEFAULT_DAILY_MINUTES;

    // Form
    public bool $showForm = false;
    public ?string $editingId = null;
    public string $exerciseTypeId = '';
    public ?int $sets = null;
    public ?int $reps = null;
    public ?int $duration = null;
    public ?float $distance = null;
    public ?float $weight = null;
    public ?int $calories = null;
    public ?int $steps = null;
    public string $notes = '';

    public function mount()
    {
        $this->initializeSelectedDate();
        $this->setDateScope($this->dateScope);
        $this->calendarMonth = $this->normalizeCalendarMonth($this->calendarMonth);
        $this->dailyGoal = auth()->user()->daily_exercise_minutes ?: ExerciseProgress::DEFAULT_DAILY_MINUTES;
    }

    public function updatedSort(): void
    {
        $this->sort = array_key_exists($this->sort, self::SORTS) ? $this->sort : 'recent';
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function applyFilters(string $scope, string $type): void
    {
        $this->setDateScope($scope);
        $this->typeFilter = $type !== '' && ExerciseType::whereKey($type)->exists() ? $type : '';
        $this->resetPage();
    }

    public function removeFilter(string $key, ?string $value = null): void
    {
        $this->resetPage();
        match ($key) {
            'date' => $this->dateScope = 'all',
            'type' => $this->typeFilter = '',
            default => null,
        };
    }

    public function clearFilters(): void
    {
        $this->resetPage();
        $this->dateScope = 'all';
        $this->typeFilter = '';
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
        $this->resetForm();

        if ($id) {
            $log = ExerciseLog::find($id);
            if ($log) {
                $this->editingId = $id;
                $this->exerciseTypeId = $log->exercise_type_id ?? '';
                $this->sets = $log->sets;
                $this->reps = $log->reps;
                $this->duration = $log->duration;
                $this->distance = $log->distance;
                $this->weight = $log->weight;
                $this->calories = $log->calories;
                $this->steps = $log->steps;
                $this->notes = $log->notes ?? '';
            }
        }

        $this->showForm = true;
    }

    public function closeForm()
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->resetForm();
    }

    public function updatedExerciseTypeId()
    {
        if (!$this->editingId) {
            $this->estimateFromDuration();
        }
    }

    public function updatedDuration()
    {
        $this->estimateFromDuration();
    }

    /** Recalcula calorías y pasos sugeridos; el usuario puede sobrescribirlos después. */
    private function estimateFromDuration(): void
    {
        if (!$this->exerciseTypeId || !$this->duration) {
            return;
        }

        $type = ExerciseType::find($this->exerciseTypeId);
        if (!$type) {
            return;
        }

        $estimate = LogExercise::estimate($type, $this->duration);
        $this->calories = $estimate['calories'] ?? $this->calories;
        $this->steps = $estimate['steps'] ?? $this->steps;
    }

    /** Chat del compañero: registra 30 min del ejercicio elegido. */
    public function quickAdd(string $exerciseTypeId, int $minutes = 30)
    {
        $type = ExerciseType::find($exerciseTypeId);
        if (! $type) {
            return ['ok' => false, 'message' => 'No encontré ese ejercicio.'];
        }

        \App\Actions\LogExercise::handle($type, $this->selectedDate, ['duration' => $minutes]);

        return ['ok' => true, 'minutes' => (int) ExerciseLog::whereDate('date', $this->selectedDate)->sum('duration')];
    }

    public function save()
    {
        if (!$this->exerciseTypeId) return;

        $data = [
            'date' => $this->selectedDate,
            'exercise_type_id' => $this->exerciseTypeId,
            'sets' => $this->sets,
            'reps' => $this->reps,
            'duration' => $this->duration,
            'distance' => $this->distance,
            'weight' => $this->weight,
            'calories' => $this->calories,
            'steps' => $this->steps,
            'notes' => $this->notes ?: null,
        ];

        if ($this->editingId) {
            $log = ExerciseLog::find($this->editingId);
            if ($log) {
                // Editar desde «Todas las fechas» no mueve el registro al día seleccionado.
                unset($data['date']);
                $log->update($data);
            }
        } else {
            ExerciseLog::create($data);
        }

        $this->closeForm();
    }

    public function delete(string $id)
    {
        ExerciseLog::where('id', $id)->delete();
    }

    private function resetForm()
    {
        $this->exerciseTypeId = '';
        $this->sets = null;
        $this->reps = null;
        $this->duration = null;
        $this->distance = null;
        $this->weight = null;
        $this->calories = null;
        $this->steps = null;
        $this->notes = '';
    }

    public function render()
    {
        $dayLogs = ExerciseLog::whereDate('date', $this->selectedDate);
        $search = trim($this->search);
        $logs = $this->applyDateScope(ExerciseLog::query())
            ->with('exerciseType')
            ->when($search !== '', fn ($q) => $q->where(fn ($inner) => $inner
                ->whereHas('exerciseType', fn ($type) => $type->where('name', 'like', '%'.$search.'%'))
                ->orWhere('notes', 'like', '%'.$search.'%')))
            ->when($this->typeFilter !== '', fn ($q) => $q->where('exercise_type_id', $this->typeFilter))
            ->when(in_array($this->sort, ['duration', 'calories'], true), fn ($q) => $q->orderByDesc($this->sort))
            // Sin columna de hora: el momento del registro es fecha + created_at.
            ->orderBy('date', $this->sort === 'oldest' ? 'asc' : 'desc')
            ->orderBy('created_at', $this->sort === 'oldest' ? 'asc' : 'desc')
            ->paginate($this->perPage());

        $picker = app(\App\Services\Catalog\CatalogPicker::class);
        $exerciseTypes = $picker->ordered('exercise');
        $quickExercises = $picker->quick('exercise', 4, $exerciseTypes);
        $typeFilterLabel = $this->typeFilter !== '' ? $exerciseTypes->firstWhere('id', $this->typeFilter)?->name : null;
        $activeFilters = $this->dateFilterChips();
        if ($typeFilterLabel) {
            $activeFilters[] = ['key' => 'type', 'value' => null, 'label' => 'Tipo: '.$typeFilterLabel, 'icon' => 'bi-tag'];
        }

        // Los totales del día no dependen de la página visible.
        $totalCalories = (clone $dayLogs)->sum('calories');
        $totalDuration = (clone $dayLogs)->sum('duration');
        $totalSteps = (clone $dayLogs)->sum('steps');

        return view('livewire.exercise.exercise-daily', [
            'quickExercises' => $quickExercises,
            'favoriteIds' => $picker->favoriteIds('exercise'),
            'logs' => $logs,
            'sorts' => self::SORTS,
            'totalLogs' => ExerciseLog::count(),
            'activeFilters' => $activeFilters,
            'dateScopes' => $this->dateScopeOptions(),
            'exerciseTypes' => $exerciseTypes,
            'totalCalories' => $totalCalories,
            'totalDuration' => $totalDuration,
            // Sin tope: el usuario puede superar su meta; la barra se limita en la vista.
            'rawPercentage' => $this->dailyGoal > 0 ? (int) round(($totalDuration / $this->dailyGoal) * 100) : 0,
            'monthData' => $this->monthData(),
            'streak' => GoalCalendar::streak(ExerciseProgress::totals(today()->subDays(366), today()), $this->dailyGoal),
            'totalSteps' => $totalSteps,
        ]);
    }

    private function monthData(): array
    {
        $selected = Carbon::parse($this->selectedDate);
        $monthStart = Carbon::parse($this->normalizeCalendarMonth($this->calendarMonth).'-01');
        [$gridStart, $gridEnd] = GoalCalendar::gridBounds($selected, $monthStart);

        return GoalCalendar::month(ExerciseProgress::totals($gridStart, $gridEnd), $selected, $this->dailyGoal, $monthStart);
    }
}
