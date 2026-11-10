<?php

namespace App\Livewire\Habit;

use App\Livewire\Concerns\HandlesHabitActionPrompt;
use App\Livewire\Concerns\HasUrlDate;
use App\Models\Game\GameTeamMember;
use App\Models\HabitCompletion;
use App\Models\HabitDefinition;
use App\Services\Game\SpeciesImageProvider;
use App\Services\Game\WeekPlanner;
use App\Services\HabitGamificationService;
use App\Support\HabitProgress;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Hábitos')]
class HabitTracker extends Component
{
    use HandlesHabitActionPrompt;
    use HasUrlDate;

    public ?array $lastHabitFeedback = null;

    /** Modo rutina: franja, cola de hábitos pendientes, posición y conteos. */
    public ?array $routine = null;

    public function mount()
    {
        $this->initializeSelectedDate();
    }

    public function toggleHabit(int $habitId, HabitGamificationService $gamification): void
    {
        $this->lastHabitFeedback = $gamification->toggle($habitId, $this->selectedDate);
        $this->dispatch('habit-feedback', ...$this->lastHabitFeedback);

        $this->openHabitActionPrompt(
            $habitId,
            HabitDefinition::find($habitId)?->name ?? '',
            $this->lastHabitFeedback,
        );
    }

    public function startRoutine(string $period): void
    {
        $today = now()->toDateString();
        $doneIds = HabitCompletion::whereDate('date', $today)->where('completed', true)->pluck('habit_id')->all();
        $queue = HabitDefinition::query()
            ->where('time_of_day', $period)
            ->whereNotIn('id', $doneIds)
            ->orderBy('base_time')
            ->pluck('id')
            ->all();

        if ($queue === []) {
            return;
        }

        $this->selectedDate = $today;
        $this->routine = [
            'period' => $period,
            'queue' => $queue,
            'index' => 0,
            'status' => array_fill_keys(array_map('strval', $queue), 'pending'),
            'companion' => $this->routineCompanion(),
        ];
    }

    public function routineDone(HabitGamificationService $gamification): void
    {
        $habitId = $this->currentRoutineHabitId();
        if ($habitId === null) {
            return;
        }

        $alreadyDone = HabitCompletion::where('habit_id', $habitId)
            ->whereDate('date', $this->selectedDate)
            ->where('completed', true)
            ->exists();

        if (! $alreadyDone) {
            $this->toggleHabit($habitId, $gamification);
        }

        $this->routine['status'][(string) $habitId] = 'done';
        $this->routine['index']++;
    }

    public function routineSkip(): void
    {
        $habitId = $this->currentRoutineHabitId();
        if ($habitId === null) {
            return;
        }

        if ($this->routine['status'][(string) $habitId] !== 'done') {
            $this->routine['status'][(string) $habitId] = 'skipped';
        }
        $this->routine['index']++;
    }

    public function routineGoTo(int $index): void
    {
        if ($this->routine && $index >= 0 && $index < count($this->routine['queue'])) {
            $this->routine['index'] = $index;
        }
    }

    public function routinePrevious(): void
    {
        if ($this->routine) {
            $this->routineGoTo(max(0, $this->routine['index'] - 1));
        }
    }

    /** Vuelve a recorrer solo los hábitos saltados. */
    public function routineRetrySkipped(): void
    {
        if (! $this->routine) {
            return;
        }

        $skipped = array_values(array_map('intval', array_keys(array_filter(
            $this->routine['status'],
            fn ($state) => $state === 'skipped',
        ))));

        if ($skipped !== []) {
            $this->routine['queue'] = $skipped;
            $this->routine['index'] = 0;
        }
    }

    public function closeRoutine(): void
    {
        $this->routine = null;
    }

    /** Segundos a partir de textos como «2 min», «1 h» o «90 s»; null si no se entiende. */
    public static function routineSeconds(?string $duration): ?int
    {
        if (! $duration || ! preg_match('/(\d+(?:[.,]\d+)?)\s*(h|hora|horas|min|mins|minutos?|m|s|seg|segundos?)?/iu', $duration, $m)) {
            return null;
        }

        $value = (float) str_replace(',', '.', $m[1]);
        $unit = mb_strtolower($m[2] ?? 'min');
        $seconds = match (true) {
            str_starts_with($unit, 'h') => $value * 3600,
            str_starts_with($unit, 's') => $value,
            default => $value * 60,
        };

        return $seconds > 0 ? (int) round($seconds) : null;
    }

    private function currentRoutineHabitId(): ?int
    {
        return $this->routine['queue'][$this->routine['index'] ?? 0] ?? null;
    }

    /** Primer integrante del equipo semanal; la rutina funciona igual sin él. */
    private function routineCompanion(): ?array
    {
        try {
            $week = app(WeekPlanner::class)->forWeek((int) auth()->id(), now());
            $member = GameTeamMember::with('companion.species')
                ->where('game_week_id', $week->id)
                ->orderBy('slot')
                ->first();
            $species = $member?->companion?->species;

            return $species ? [
                'name' => $species->name,
                'image' => app(SpeciesImageProvider::class)->url($species),
            ] : null;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    public function previousDay()
    {
        $this->selectedDate = Carbon::parse($this->selectedDate)->subDay()->toDateString();
        $this->lastHabitFeedback = null;
        $this->skipHabitAction();
    }

    public function nextDay()
    {
        $this->selectedDate = Carbon::parse($this->selectedDate)->addDay()->toDateString();
        $this->lastHabitFeedback = null;
        $this->skipHabitAction();
    }

    public function today()
    {
        $this->selectedDate = now()->toDateString();
        $this->lastHabitFeedback = null;
        $this->skipHabitAction();
    }

    public function render(HabitGamificationService $gamification)
    {
        $habits = HabitDefinition::orderBy('base_time')->get();
        $completions = HabitCompletion::whereDate('date', $this->selectedDate)
            ->pluck('completed', 'habit_id')
            ->toArray();

        $groupedHabits = $habits->groupBy('time_of_day');
        $completedCount = count(array_filter($completions));
        $totalCount = $habits->count();

        return view('livewire.habit.habit-tracker', [
            'groupedHabits' => $groupedHabits,
            'completions' => $completions,
            'completedCount' => $completedCount,
            'totalCount' => $totalCount,
            'monthData' => HabitProgress::month(Carbon::parse($this->selectedDate)),
            'initiallyOpenTimeOfDay' => $this->initiallyOpenTimeOfDay(),
            'isToday' => $this->selectedDate === now()->toDateString(),
            'routineHabit' => ($id = $this->currentRoutineHabitId()) ? $habits->firstWhere('id', $id) : null,
            'routineHabits' => $this->routine ? $habits->keyBy('id') : collect(),
            // CHG-058: con guardián, el coach pasa a ser uno de sus consejos (panel derecho).
            'hasGuardian' => \App\Livewire\Game\GuardianCard::visibleFor((int) auth()->id(), 'habits'),
            'coachCard' => $gamification->coachCard(
                Carbon::parse($this->selectedDate),
                $habits,
                $completions,
                $this->lastHabitFeedback,
            ),
        ]);
    }

    private function initiallyOpenTimeOfDay(): array
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
}
