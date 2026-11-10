<?php

namespace App\Services\Game\Guardian;

use App\Models\ModuleSetting;
use App\Models\PomodoroSession;
use App\Models\Task;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/** Consejos de Pomodoro (CHG-058): foco que falta, tarea con más foco, mejor franja y bloque para la tarea de ahora. 3 consultas. */
class PomodoroAdvice
{
    use Advice;

    /** Meta diaria en minutos, igual que el temporizador (entre semana / fin de semana). */
    public static function goalFor(CarbonInterface $date): int
    {
        $settings = ModuleSetting::where('module', 'pomodoro')->value('settings') ?? [];

        return $date->isWeekend()
            ? (int) ($settings['weekend_goal_minutes'] ?? 120)
            : (int) ($settings['weekday_goal_minutes'] ?? 300);
    }

    /** @return list<array{key: string, html: string, weight: int}> */
    public function candidates(CarbonInterface $now): array
    {
        $out = [];
        $today = $now->copy()->startOfDay();
        $sessions = PomodoroSession::query()->whereBetween('date', [$today->copy()->subDays(14), $now->copy()->endOfDay()])
            ->get(['task_id', 'task_title', 'date', 'start_time', 'duration']);
        $todaySessions = $sessions->filter(fn ($s) => $s->date->isSameDay($today));

        $goal = self::goalFor($now);
        $focus = intdiv((int) $todaySessions->sum('duration'), 60);
        if ($goal > 0 && $focus < $goal) {
            $out[] = $this->candidate('pomodoro', 'left', ['minutes' => $goal - $focus], 2);
        }

        $top = $sessions->filter(fn ($s) => $s->task_title && $s->date->gte($today->copy()->subDays(6)))
            ->groupBy('task_title')->map(fn ($g) => intdiv((int) $g->sum('duration'), 60))->sortDesc();
        if ($top->isNotEmpty() && $top->first() > 0) {
            $out[] = $this->candidate('pomodoro', 'top', ['task' => $top->keys()->first(), 'minutes' => $top->first()], 1, ['task']);
        }

        $slot = fn (int $hour) => match (true) { $hour < 12 => 'mañana', $hour < 18 => 'tarde', default => 'noche' };
        $bySlot = $sessions->filter(fn ($s) => isset($s->start_time['timestamp']))
            ->groupBy(fn ($s) => $slot(Carbon::createFromTimestamp($s->start_time['timestamp'], config('app.timezone'))->hour))
            ->map(fn ($g) => (int) $g->sum('duration'))->sortDesc();
        if ($bySlot->isNotEmpty() && $bySlot->keys()->first() === $slot($now->hour)) {
            $out[] = $this->candidate('pomodoro', 'slot', ['slot' => $bySlot->keys()->first()], 2);
        }

        $current = Task::query()->where('completed', false)->where('start_is_date', false)
            ->where('start_date', '<=', $now)->where('end_date', '>=', $now)->orderBy('start_date')->first(['id', 'title']);
        if ($current && ! $todaySessions->contains('task_id', $current->id)) {
            $out[] = $this->candidate('pomodoro', 'now', ['task' => $current->title], 3, ['task']);
        }

        return $out;
    }
}
