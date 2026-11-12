<?php

namespace App\Actions\Task;

use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Mueve tareas con Deshacer (CHG-072, reutilizado en el cierre del día CHG-070).
 * Corre el bloque entero para que la fecha límite caiga en el día elegido, sin
 * cambiar la hora ni la duración.
 */
class ShiftTasksToDate
{
    /**
     * Aplica un cambio a cada tarea y devuelve sus fechas previas para Deshacer.
     *
     * @param  iterable<Task>  $tasks
     * @return array<int|string, array{start: ?string, end: ?string}>
     */
    public static function apply(iterable $tasks, callable $change): array
    {
        $undo = [];
        DB::transaction(function () use ($tasks, $change, &$undo) {
            foreach ($tasks as $task) {
                $undo[$task->id] = [
                    'start' => $task->start_date?->toDateTimeString(),
                    'end' => $task->end_date?->toDateTimeString(),
                ];
                $change($task);
                $task->save();
            }
        });

        return $undo;
    }

    /** @param  iterable<Task>  $tasks */
    public static function handle(iterable $tasks, CarbonImmutable $target): array
    {
        $target = $target->startOfDay();

        return self::apply($tasks, function (Task $task) use ($target) {
            $deadline = CarbonImmutable::instance($task->end_date ?? $task->start_date);
            $shift = (int) $deadline->startOfDay()->diffInDays($target, false);
            $task->fill([
                'start_date' => $task->start_date?->copy()->addDays($shift),
                'end_date' => $task->end_date?->copy()->addDays($shift),
            ]);
        });
    }

    /** @param  array<int|string, array{start: ?string, end: ?string}>  $undo */
    public static function restore(array $undo): void
    {
        DB::transaction(function () use ($undo) {
            foreach (Task::whereIn('id', array_keys($undo))->get() as $task) {
                $task->fill(['start_date' => $undo[$task->id]['start'], 'end_date' => $undo[$task->id]['end']])->save();
            }
        });
    }
}
