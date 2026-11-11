<?php

namespace App\Livewire\Statistics;

use App\Models\DrinkLog;
use App\Models\EnergyEntry;
use App\Models\ExerciseLog;
use App\Models\HabitCompletion;
use App\Models\HabitDefinition;
use App\Models\JournalEntry;
use App\Models\MoodEntry;
use App\Models\Task;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

#[Layout('layouts.app')]
#[Title('Estadísticas')]
class StatisticsDashboard extends Component
{
    #[Url(as: 'days', history: true, keep: true)]
    public int $days = 7;

    public function mount(): void
    {
        $this->normalizeDays();
    }

    public function updatedDays(): void
    {
        $this->normalizeDays();
    }

    private function normalizeDays(): void
    {
        if (!in_array($this->days, [7, 14, 30], true)) {
            $this->days = 7;
        }
    }

    public function render()
    {
        $endDate = now();
        $startDate = now()->subDays($this->days - 1);
        $dates = [];
        for ($d = $startDate->copy(); $d->lte($endDate); $d->addDay()) {
            $dates[] = $d->toDateString();
        }

        // Water data
        $waterData = DrinkLog::whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->selectRaw('date, SUM(hydration_value) as total')
            ->groupBy('date')
            ->pluck('total', 'date')
            ->toArray();

        // Exercise data
        $exerciseData = ExerciseLog::whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->selectRaw('date, SUM(calories) as total')
            ->groupBy('date')
            ->pluck('total', 'date')
            ->toArray();

        // Habits data
        $totalHabits = HabitDefinition::count();
        $habitsData = HabitCompletion::whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->where('completed', true)
            ->selectRaw('date, COUNT(*) as total')
            ->groupBy('date')
            ->pluck('total', 'date')
            ->toArray();

        // Mood data
        $moodData = MoodEntry::whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->selectRaw('date, AVG(value) as avg_value')
            ->groupBy('date')
            ->pluck('avg_value', 'date')
            ->toArray();

        // Energy data
        $energyData = EnergyEntry::whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->selectRaw('date, AVG(level) as avg_level')
            ->groupBy('date')
            ->pluck('avg_level', 'date')
            ->toArray();

        // Journal data
        $journalDays = JournalEntry::whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->pluck('date')
            ->map(fn($d) => $d->format('Y-m-d'))
            ->toArray();

        // Tasks stats
        $tasksCompletedCount = Task::where('completed', true)->count();
        $tasksPendingCount = Task::where('completed', false)->count();

        // Summary stats
        $avgWater = count($waterData) > 0 ? array_sum($waterData) / count($waterData) : 0;
        $avgExercise = count($exerciseData) > 0 ? array_sum($exerciseData) / count($exerciseData) : 0;
        $avgHabits = count($habitsData) > 0 ? array_sum($habitsData) / count($habitsData) : 0;
        $journalCount = count($journalDays);

        return view('livewire.statistics.statistics-dashboard', [
            // CHG-071: hallazgos de la última semana cerrada (domingo); se calculan una vez por semana.
            'insights' => app(\App\Services\Insights\WeeklyInsights::class)->forWeek(auth()->user(), now()->isSunday() ? now() : now()->previous(\Carbon\Carbon::SUNDAY))->findings,
            'dates' => $dates,
            'waterData' => $waterData,
            'exerciseData' => $exerciseData,
            'habitsData' => $habitsData,
            'moodData' => $moodData,
            'energyData' => $energyData,
            'journalDays' => $journalDays,
            'totalHabits' => $totalHabits,
            'tasksCompletedCount' => $tasksCompletedCount,
            'tasksPendingCount' => $tasksPendingCount,
            'avgWater' => $avgWater,
            'avgExercise' => $avgExercise,
            'avgHabits' => $avgHabits,
            'journalCount' => $journalCount,
        ]);
    }
}
