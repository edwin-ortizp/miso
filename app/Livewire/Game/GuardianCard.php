<?php

namespace App\Livewire\Game;

use App\Models\HealthEvent;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Services\Game\Guardian\GuardianAdvisor;
use App\Services\Game\ModuleGuardian;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Guardián de módulo con consejo (CHG-055/057). Componente hijo aislado: el
 * botón "Otro consejo" y el refresco cada 5 min solo re-renderizan la franja.
 */
class GuardianCard extends Component
{
    public string $module = 'tasks';

    /** `top`: arriba de la lista en pantallas angostas; `rail`: dentro del panel lateral. */
    public string $variant = 'rail';

    public ?array $guardian = null;

    public string $adviceKey = '';

    public string $advice = '';

    /** Acción opcional del consejo (CHG-058), p. ej. "Ir al hábito sugerido". */
    public ?array $action = null;

    /** CHG-062: ficha del guardián en un modal (se arma solo cuando está abierto). */
    public bool $showSheet = false;

    public function mount(): void
    {
        $this->guardian = app(ModuleGuardian::class)->pick((int) auth()->id(), $this->module);
        if ($this->guardian) {
            $this->advise(null);
        }
    }

    public function openSheet(): void
    {
        $this->showSheet = $this->guardian !== null;
    }

    public function another(): void
    {
        $this->advise($this->adviceKey);
    }

    #[On('task-records-changed')]
    public function refreshAdvice(): void
    {
        $this->advise(null);
    }

    private function advise(?string $except): void
    {
        if (! $this->guardian) {
            return;
        }
        $advice = app(GuardianAdvisor::class)->advise($this->module, now(), $except, $this->progress());
        $this->adviceKey = $advice['key'];
        $this->advice = $advice['html'];
        $this->action = $advice['action'] ?? null;
    }

    /** @return array{done: int, total: int, care: bool} Datos de la frase por avance (CHG-055). */
    private function progress(): array
    {
        if ($this->module === 'health') {
            $tasks = Task::query()->whereIn('category', TaskCategory::expandSelection(['salud'], auth()->id(), 'key'));
            $done = (clone $tasks)->where('completed', true)->where('completed_at', '>=', now()->subDays(7))->count();

            return [
                'done' => $done,
                'total' => $done + (clone $tasks)->where('completed', false)->count(),
                'care' => HealthEvent::query()->whereIn('type', HealthEvent::EVOLUTION_TYPES)->whereNull('end_date')->exists(),
            ];
        }

        $day = [today()->startOfDay(), today()->endOfDay()];
        $goalOf = fn (int $done, int $goal) => ['done' => $done, 'total' => $goal, 'care' => false];
        switch ($this->module) {
            case 'water':
                return $goalOf((int) \App\Models\DrinkLog::query()->whereBetween('date', $day)->sum('hydration_value'), \App\Support\WaterGoal::forUser(auth()->user()));
            case 'exercise':
                return $goalOf((int) \App\Models\ExerciseLog::query()->whereBetween('date', $day)->sum('duration'),
                    (int) (auth()->user()->daily_exercise_minutes ?: \App\Support\ExerciseProgress::DEFAULT_DAILY_MINUTES));
            case 'pomodoro':
                return $goalOf(intdiv((int) \App\Models\PomodoroSession::query()->whereBetween('date', $day)->sum('duration'), 60),
                    \App\Services\Game\Guardian\PomodoroAdvice::goalFor(today()));
            case 'habits':
                return $goalOf(\App\Models\HabitCompletion::query()->whereBetween('date', $day)->where('completed', true)->count(),
                    \App\Models\HabitDefinition::count());
        }

        if ($this->module !== 'tasks') {
            return ['done' => 0, 'total' => 0, 'care' => false];
        }

        $today = today()->toDateString();
        $stats = Task::selectRaw('
            SUM(CASE WHEN completed = 1 AND DATE(completed_at) = ? THEN 1 ELSE 0 END) as done,
            SUM(CASE WHEN completed = 0 AND (DATE(start_date) = ? OR DATE(end_date) = ?) THEN 1 ELSE 0 END) as planned
        ', [$today, $today, $today])->first();

        return ['done' => (int) $stats->done, 'total' => (int) $stats->planned, 'care' => false];
    }

    /** Si el usuario tiene guardián en este módulo (para ocultar el coach antiguo de Hábitos). */
    public static function visibleFor(int $userId, string $module): bool
    {
        return app(ModuleGuardian::class)->pick($userId, $module) !== null;
    }

    public function render()
    {
        return view('livewire.game.guardian-card', [
            'refresh' => (int) config('game.guardian_advice.refresh_seconds', 300),
            'sheet' => $this->showSheet && $this->guardian
                ? app(\App\Services\Game\SpeciesSheet::class)->build((int) $this->guardian['species_id'])
                : null,
        ]);
    }
}
