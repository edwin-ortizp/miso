<?php

namespace App\Livewire\Home;

use App\Actions\LogDrink;
use App\Livewire\Concerns\HandlesHabitActionPrompt;
use App\Livewire\Concerns\HandlesRecurringTaskCompletion;
use App\Livewire\Concerns\HasUrlDate;
use App\Livewire\Concerns\LogsMoodProgressively;
use App\Models\DrinkLog;
use App\Models\DrinkType;
use App\Models\EnergyEntry;
use App\Models\HabitCompletion;
use App\Models\HabitDefinition;
use App\Models\JournalEntry;
use App\Models\MealPlanEntry;
use App\Models\MoodEntry;
use App\Models\Task;
use App\Models\Vehicle;
use App\Models\VehicleMaintenancePlan;
use App\Services\HabitGamificationService;
use App\Services\TaskGamificationService;
use App\Support\VehicleMaintenanceStatus;
use App\Support\VehicleUsageProjection;
use App\Support\WaterGoal;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Inicio')]
class Dashboard extends Component
{
    use HandlesHabitActionPrompt, HandlesRecurringTaskCompletion, HasUrlDate, LogsMoodProgressively;

    /** CHG-069: hoja de check-in de 20 s (`/?checkin=1`, botón en Inicio o acción del aviso). */
    #[Url(as: 'checkin', except: false)]
    public bool $checkin = false;

    /** El check-in siempre registra hoy. */
    public function updatedCheckin(bool $open): void
    {
        if ($open) {
            $this->selectedDate = now()->toDateString();
        }
    }

    public function closeCheckin(): void
    {
        $this->checkin = false;
    }

    public const MEAL_TYPES = [
        'desayuno' => 'Desayuno',
        'comida' => 'Onces (mañana)',
        'almuerzo' => 'Almuerzo',
        'merienda' => 'Merienda (tarde)',
        'cena' => 'Cena',
    ];

    /** Integrante del día (CHG-043): se elige al cargar y no cambia con los refrescos. @var array<string, mixed>|null */
    public ?array $buddy = null;

    public function mount()
    {
        $this->buddy = app(\App\Services\Game\DailyBuddy::class)->pick((int) auth()->id());
        $this->initializeSelectedDate();
        if ($this->checkin) {
            $this->selectedDate = now()->toDateString();
        }
    }

    public function previousDay()
    {
        $this->selectedDate = Carbon::parse($this->selectedDate)->subDay()->toDateString();
    }

    public function nextDay()
    {
        $this->selectedDate = Carbon::parse($this->selectedDate)->addDay()->toDateString();
    }

    public function today()
    {
        $this->selectedDate = now()->toDateString();
    }

    public function quickAddWater(string $drinkTypeId, int $amount): void
    {
        $drinkType = DrinkType::find($drinkTypeId);
        if (! $drinkType || $amount <= 0) {
            return;
        }

        LogDrink::handle($drinkType, $this->selectedDate, $amount);
    }

    /** One tap through the shared logger, so Inicio matches Ánimo and Diario exactly. */
    #[On('panel-changed')]
    #[On('task-calendar-refresh')]
    public function refreshDashboard(): void {}

    #[On('panel-mood-recorded')]
    public function moodRecorded(string $id): void
    {
        $this->lastMoodEntryId = MoodEntry::findOrFail($id)->id;
    }

    public function saveMood(string $moodStateId): void
    {
        $this->logMood($moodStateId);
    }

    public function saveEnergy(int $level): void
    {
        if ($level < 1 || $level > 5) {
            return;
        }

        $now = now();

        EnergyEntry::create([
            'date' => $this->selectedDate,
            'level' => $level,
            'time' => $now->format('H:i'),
            'timestamp' => $now->timestamp,
        ]);
    }

    public function toggleHabit(int $habitId, HabitGamificationService $gamification): void
    {
        $feedback = $gamification->toggle($habitId, $this->selectedDate);
        $this->dispatch('habit-feedback', ...$feedback);

        $this->openHabitActionPrompt(
            $habitId,
            HabitDefinition::find($habitId)?->name ?? '',
            $feedback,
        );
    }

    public function toggleTask(string $id, TaskGamificationService $gamification): void
    {
        $task = Task::find($id);
        if (! $task) {
            return;
        }

        if ($this->prepareRecurringCompletion($task)) {
            return;
        }

        $result = $gamification->toggle($task);

        if ($result['completed']) {
            $this->dispatch('task-completed', ...$result);
        }
    }

    public function render()
    {
        $date = $this->selectedDate;

        // Water stats
        $waterTotal = DrinkLog::where('date', $date)->sum('hydration_value');
        $waterGoal = WaterGoal::forUser(auth()->user());
        $drinkTypes = DrinkType::orderBy('name')->get();

        // Mood & energy
        $lastMood = MoodEntry::whereDate('date', $date)->latest('timestamp')->first();
        $lastEnergy = EnergyEntry::whereDate('date', $date)->latest('timestamp')->first();
        $moodStates = $this->moodLogger()->prioritizedStates();

        // Habits: pending ones for the current time block + anytime
        $habits = HabitDefinition::orderBy('base_time')->get();
        $completions = HabitCompletion::whereDate('date', $date)
            ->pluck('completed', 'habit_id')
            ->toArray();
        $completedHabits = count(array_filter($completions));
        $totalHabits = $habits->count();
        $activeTimesOfDay = $this->activeTimesOfDay();
        $pendingHabits = $habits
            ->filter(fn ($habit) => empty($completions[$habit->id]))
            ->filter(fn ($habit) => in_array($habit->time_of_day, $activeTimesOfDay, true))
            ->values();

        // Top tasks for the selected day (planned for the day or overdue), Eisenhower order
        $todayTasks = Task::where('completed', false)
            ->where(fn ($q) => $q
                ->whereDate('start_date', $date)
                ->orWhereDate('end_date', $date)
                ->orWhere(fn ($q2) => $q2->where('end_date', '<', now()))
            )
            ->orderByRaw('CASE WHEN priority = "urgent-important" THEN 1 WHEN priority = "not-urgent-important" THEN 2 WHEN priority = "urgent-not-important" THEN 3 ELSE 4 END')
            ->orderBy('end_date')
            ->take(5)
            ->get();
        $pendingTasks = Task::where('completed', false)->count();
        $completedTasksToday = Task::where('completed', true)
            ->whereDate('completed_at', $date)->count();

        // Meals planned for the day, in meal-type order
        $mealOrder = array_keys(self::MEAL_TYPES);
        $meals = MealPlanEntry::whereDate('date', $date)
            ->with(['items.recipe', 'items.preparation'])
            ->get()
            ->sortBy(fn ($meal) => array_search($meal->meal_type, $mealOrder))
            ->values();

        $journalEntry = JournalEntry::where('date', $date)->first();

        $plans = VehicleMaintenancePlan::where('active', true)
            ->with(['vehicle', 'template', 'maintenanceLogs'])
            ->get();

        // La proyeccion de uso depende del vehiculo, no del plan, y cada calculo
        // cuesta varias consultas sobre su historial. Se resuelve como mucho una
        // vez por vehiculo y solo si algun plan llega a necesitarla: antes se
        // repetia por cada plan aunque compartieran vehiculo.
        $usageRates = [];
        $resolveUsageRate = function (?Vehicle $vehicle) use (&$usageRates) {
            if (! $vehicle) {
                return false;
            }

            return $usageRates[$vehicle->id] ??= VehicleUsageProjection::rateForVehicle($vehicle) ?? false;
        };

        $vehicleAlerts = $plans
            ->map(function (VehicleMaintenancePlan $plan) use ($resolveUsageRate) {
                $plan->setAttribute('status_data', VehicleMaintenanceStatus::forPlan($plan, null, $resolveUsageRate));

                return $plan;
            })
            ->filter(fn (VehicleMaintenancePlan $plan) => $plan->status_data['status'] !== 'al_dia')
            ->sortBy(fn (VehicleMaintenancePlan $plan) => $plan->status_data['status'] === 'vencido' ? 0 : 1)
            ->take(3)
            ->values();

        return view('livewire.home.dashboard', [
            'waterTotal' => $waterTotal,
            'waterGoal' => $waterGoal,
            'drinkTypes' => $drinkTypes,
            'lastMood' => $lastMood,
            'lastEnergy' => $lastEnergy,
            'moodStates' => $moodStates,
            'pendingHabits' => $pendingHabits,
            'completedHabits' => $completedHabits,
            'totalHabits' => $totalHabits,
            'todayTasks' => $todayTasks,
            'pendingTasks' => $pendingTasks,
            'completedTasksToday' => $completedTasksToday,
            'meals' => $meals,
            'mealTypes' => self::MEAL_TYPES,
            'journalEntry' => $journalEntry,
            'vehicleAlerts' => $vehicleAlerts,
            'weeklyTrend' => $this->weeklyTrend($waterGoal, $totalHabits),
            ...$this->moodProgressiveData(),
        ]);
    }

    private function activeTimesOfDay(): array
    {
        $now = Carbon::now(config('app.timezone'));

        if ($this->selectedDate !== $now->toDateString()) {
            return ['morning', 'afternoon', 'night', 'anytime'];
        }

        if ($now->hour >= 6 && $now->hour < 12) {
            return ['morning', 'anytime'];
        }

        if ($now->hour >= 12 && $now->hour < 18) {
            return ['afternoon', 'anytime'];
        }

        return ['night', 'anytime'];
    }

    private function weeklyTrend(int $waterGoal, int $totalHabits): array
    {
        $end = Carbon::parse($this->selectedDate);
        $start = $end->copy()->subDays(6);

        $waterByDay = DrinkLog::whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->selectRaw('DATE(date) as day, SUM(hydration_value) as total')
            ->groupBy('day')
            ->toBase()
            ->pluck('total', 'day');

        $habitsByDay = HabitCompletion::whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->where('completed', true)
            ->selectRaw('DATE(date) as day, COUNT(*) as total')
            ->groupBy('day')
            ->toBase()
            ->pluck('total', 'day');

        $trend = [];
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $key = $day->toDateString();
            $trend[] = [
                'date' => $day->translatedFormat('D d'),
                'water' => $waterGoal > 0 ? min(100, (int) round(($waterByDay[$key] ?? 0) / $waterGoal * 100)) : 0,
                'habits' => $totalHabits > 0 ? (int) round(($habitsByDay[$key] ?? 0) / $totalHabits * 100) : 0,
            ];
        }

        return $trend;
    }
}
