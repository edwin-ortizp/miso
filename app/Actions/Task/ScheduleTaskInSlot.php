<?php

namespace App\Actions\Task;

use App\Models\Task;
use Carbon\CarbonInterface;

/**
 * CHG-143: agenda una tarea en un hueco libre (inicio, fin y duración) y devuelve lo necesario para deshacerlo.
 */
final class ScheduleTaskInSlot
{
    /** @return array{start_date: ?string, start_is_date: bool, end_date: ?string, end_is_date: bool, estimated_time: ?int} */
    public static function handle(Task $task, CarbonInterface $start, int $minutes): array
    {
        $undo = [
            'start_date' => $task->start_date?->toDateTimeString(),
            'start_is_date' => (bool) $task->start_is_date,
            'end_date' => $task->end_date?->toDateTimeString(),
            'end_is_date' => (bool) $task->end_is_date,
            'estimated_time' => $task->estimated_time,
        ];

        $task->update([
            'start_date' => $start,
            'start_is_date' => false,
            'end_date' => $start->copy()->addMinutes($minutes),
            'end_is_date' => false,
            'estimated_time' => $minutes,
        ]);

        return $undo;
    }

    /** @param  array<string, mixed>  $undo */
    public static function restore(Task $task, array $undo): void
    {
        $task->update([
            'start_date' => $undo['start_date'] ?? null,
            'start_is_date' => (bool) ($undo['start_is_date'] ?? true),
            'end_date' => $undo['end_date'] ?? null,
            'end_is_date' => (bool) ($undo['end_is_date'] ?? true),
            'estimated_time' => $undo['estimated_time'] ?? null,
        ]);
    }
}
