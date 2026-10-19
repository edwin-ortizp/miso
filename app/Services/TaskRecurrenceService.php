<?php

namespace App\Services;

use App\Models\Task;
use App\Services\CalDav\RecurrenceRule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TaskRecurrenceService
{
    public function __construct(private readonly RecurrenceRule $recurrenceRule) {}

    public const PATTERNS = ['daily', 'weekly', 'monthly', 'custom'];

    /**
     * Estructura `recurrence` que se guarda en la tarea. Una RRULE, si llega, manda sobre el patrón.
     * Al cambiar la regla se reinicia el conteo de ocurrencias: el ancla vuelve a ser la fecha de la tarea.
     *
     * @throws \InvalidArgumentException si la RRULE no es válida.
     */
    public function buildRecurrence(string $pattern, int $interval = 1, ?string $rrule = null): array
    {
        if (filled($rrule)) {
            return ['pattern' => 'custom', 'frequency' => 1, 'rrule' => $this->recurrenceRule->normalize($rrule)];
        }

        $interval = max(1, $interval);

        return $pattern === 'custom' || ! in_array($pattern, self::PATTERNS, true)
            ? ['pattern' => 'custom', 'frequency' => 1, 'customDays' => $interval]
            : ['pattern' => $pattern, 'frequency' => $interval];
    }

    /** Descripción legible de la recurrencia, p. ej. «cada 2 semanas» o la RRULE. */
    public function describe(?array $recurrence): ?string
    {
        if (! $recurrence) {
            return null;
        }

        if (filled($recurrence['rrule'] ?? null) && ($recurrence['pattern'] ?? 'custom') === 'custom' && ! isset($recurrence['customDays'])) {
            return 'RRULE '.$recurrence['rrule'];
        }

        $frequency = max(1, (int) ($recurrence['frequency'] ?? 1));
        [$singular, $plural] = match ($recurrence['pattern'] ?? 'custom') {
            'daily' => ['día', 'días'],
            'weekly' => ['semana', 'semanas'],
            'monthly' => ['mes', 'meses'],
            default => ['día', 'días'],
        };
        $count = ($recurrence['pattern'] ?? 'custom') === 'custom' ? max(1, (int) ($recurrence['customDays'] ?? $frequency)) : $frequency;

        return $count === 1 ? "cada {$singular}" : "cada {$count} {$plural}";
    }

    public function suggestedNextDate(Task $task, ?Carbon $completedAt = null): Carbon
    {
        $recurrence = $task->recurrence ?? [];
        if ($rule = $this->recurrenceRule->fromTaskRecurrence($recurrence)) {
            $anchor = Carbon::parse($recurrence['anchor'] ?? ($task->start_date ?? $task->end_date ?? now()));
            $completed = (int) ($recurrence['occurrences_completed'] ?? 0) + 1;
            if ($next = $this->recurrenceRule->next($rule, $anchor, $completed)) {
                return $next;
            }
        }

        $frequency = max(1, (int) ($recurrence['frequency'] ?? 1));
        $date = ($completedAt ?? now())->copy()->startOfDay();

        return match ($recurrence['pattern'] ?? 'custom') {
            'daily' => $date->addDays($frequency),
            'weekly' => $date->addWeeks($frequency),
            'monthly' => $date->addMonthsNoOverflow($frequency),
            default => $date->addDays(max(1, (int) ($recurrence['customDays'] ?? $frequency))),
        };
    }

    /**
     * Complete the current occurrence and create the next one atomically.
     *
     * @return array{completed: bool, xp: int, streak: int, level: int, levelUp: bool, streakMilestone: bool}
     */
    public function completeAndSchedule(Task $task, Carbon $nextDate, TaskGamificationService $gamification): array
    {
        // El diálogo solo pide la fecha: una tarea con hora conserva la suya en vez de caer a medianoche.
        $timed = $task->start_date ? ! $task->start_is_date : ($task->end_date && ! $task->end_is_date);
        if ($timed) {
            $nextDate = $nextDate->copy()->setTimeFrom($task->start_date ?? $task->end_date);
        }

        return $this->completeAt($task, $nextDate, $gamification);
    }

    public function completeAndScheduleAutomatically(Task $task, TaskGamificationService $gamification, ?Carbon $completedAt = null): array
    {
        $recurrence = $task->recurrence ?? [];
        $rule = $this->recurrenceRule->fromTaskRecurrence($recurrence);
        $anchor = Carbon::parse($recurrence['anchor'] ?? ($task->start_date ?? $task->end_date ?? now()));
        $next = $rule
            ? $this->recurrenceRule->next($rule, $anchor, (int) ($recurrence['occurrences_completed'] ?? 0) + 1)
            : $this->suggestedNextDate($task);

        if (! $next) {
            return $gamification->complete($task, $completedAt);
        }

        return $this->completeAt($task, $next, $gamification, $completedAt);
    }

    private function completeAt(Task $task, Carbon $nextDate, TaskGamificationService $gamification, ?Carbon $completedAt = null): array
    {
        return DB::transaction(function () use ($task, $nextDate, $gamification, $completedAt): array {
            $current = Task::query()->lockForUpdate()->findOrFail($task->id);

            if ($current->completed || ! $current->is_recurrent) {
                return ['completed' => false, 'xp' => 0, 'streak' => 0, 'level' => 1, 'levelUp' => false, 'streakMilestone' => false];
            }

            $xp = $gamification->completionXp($current);
            $previousXp = (int) Task::where('completed', true)->sum('completion_xp');
            $seriesId = $current->recurrence_series_id ?: (string) Str::uuid();

            Task::create([
                'task_code' => $current->task_code,
                'title' => $current->title,
                'description' => $current->description,
                'category' => $current->category,
                'priority' => $current->priority,
                'size' => $current->size,
                'completed' => true,
                'completed_at' => $completedAt ?? now(),
                'completion_xp' => $xp,
                'progress' => 100,
                'is_recurrent' => false,
                'is_private' => $current->is_private,
                'start_date' => $current->start_date,
                'start_is_date' => $current->start_is_date,
                'end_date' => $current->end_date,
                'end_is_date' => $current->end_is_date,
                'estimated_time' => $current->estimated_time,
                'recurrence_series_id' => $seriesId,
                'is_recurrence_history' => true,
            ]);

            $recurrence = $current->recurrence ?? [];
            $recurrence['rrule'] = $this->recurrenceRule->fromTaskRecurrence($recurrence);
            $recurrence['anchor'] ??= ($current->start_date ?? $current->end_date ?? now())->toIso8601String();
            $recurrence['occurrences_completed'] = (int) ($recurrence['occurrences_completed'] ?? 0) + 1;
            $current->update([
                ...$this->datesForNextOccurrence($current, $nextDate),
                'recurrence' => $recurrence,
                'recurrence_series_id' => $seriesId,
                'completed' => false,
                'completed_at' => null,
                'completion_xp' => null,
                'progress' => 0,
            ]);

            $totalXp = $previousXp + $xp;
            $streaks = $gamification->streaks();

            return [
                'completed' => true,
                'xp' => $xp,
                'streak' => $streaks['current'],
                'level' => $gamification->levelForXp($totalXp),
                'levelUp' => $gamification->levelForXp($previousXp) < $gamification->levelForXp($totalXp),
                'streakMilestone' => in_array($streaks['current'], [3, 7, 14, 30, 100], true),
            ];
        });
    }

    /** @return array{start_date: Carbon|null, end_date: Carbon|null} */
    private function datesForNextOccurrence(Task $task, Carbon $nextDate): array
    {
        if ($task->start_date && $task->end_date) {
            $duration = $task->start_date->diffInSeconds($task->end_date, false);

            return ['start_date' => $nextDate, 'end_date' => $nextDate->copy()->addSeconds($duration)];
        }

        if ($task->end_date) {
            return ['start_date' => null, 'end_date' => $nextDate];
        }

        return ['start_date' => $nextDate, 'end_date' => null];
    }
}
