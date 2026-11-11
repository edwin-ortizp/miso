<?php

namespace App\Services\Game\Guardian;

use App\Models\HealthEvent;
use App\Models\Task;
use App\Models\TaskCategory;

/**
 * Avance del día por módulo (CHG-055), extraído de GuardianCard para reutilizarlo
 * en los avisos push (CHG-067). Usa los scopes del usuario autenticado.
 */
class ModuleProgress
{
    /** @return array{done: int, total: int, care: bool} */
    public function for(string $module): array
    {
        if ($module === 'health') {
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
        switch ($module) {
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

        if ($module !== 'tasks') {
            return ['done' => 0, 'total' => 0, 'care' => false];
        }

        $today = today()->toDateString();
        $stats = Task::selectRaw('
            SUM(CASE WHEN completed = 1 AND DATE(completed_at) = ? THEN 1 ELSE 0 END) as done,
            SUM(CASE WHEN completed = 0 AND (DATE(start_date) = ? OR DATE(end_date) = ?) THEN 1 ELSE 0 END) as planned
        ', [$today, $today, $today])->first();

        return ['done' => (int) $stats->done, 'total' => (int) $stats->planned, 'care' => false];
    }
}
