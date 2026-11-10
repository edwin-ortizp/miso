<?php

namespace App\Livewire\Task\Concerns;

use App\Models\Task;
use App\Models\TaskStatus;
use App\Services\TaskGamificationService;
use Illuminate\Support\Facades\DB;

trait HandlesTaskRowActions
{
    protected ?\Illuminate\Support\Collection $statusCatalog = null;

    public function completeTask(string $id, TaskGamificationService $gamification): void
    {
        if (! auth()->check()) {
            return;
        }

        DB::transaction(function () use ($id, $gamification): void {
            $task = Task::where('user_id', auth()->id())->where('completed', false)->lockForUpdate()->find($id);
            if (! $task || $this->prepareRecurringCompletion($task)) {
                return;
            }

            $result = $gamification->toggle($task);
            if ($result['completed']) {
                $this->dispatch('task-completed', ...$result);
            }
        });
    }

    public function moveToDay(string $id, int $daysFromToday): void
    {
        if (! auth()->check() || ! in_array($daysFromToday, [0, 1], true)) {
            return;
        }

        DB::transaction(function () use ($id, $daysFromToday): void {
            $task = Task::where('user_id', auth()->id())->where('completed', false)->lockForUpdate()->find($id);
            if (! $task) {
                return;
            }

            $target = today()->addDays($daysFromToday);
            $reference = $task->end_date ?? $task->start_date;
            if (! $reference) {
                $task->update(['end_date' => $target, 'end_is_date' => true]);

                return;
            }

            $shift = (int) $reference->copy()->startOfDay()->diffInDays($target, false);
            $dates = [];
            foreach (['start_date', 'end_date'] as $field) {
                if ($task->{$field}) {
                    $dates[$field] = $task->{$field}->copy()->addDays($shift);
                }
            }
            $task->update($dates);
        });
    }

    public function advanceStatus(string $id, TaskGamificationService $gamification): void
    {
        abort_unless(auth()->check(), 403);
        $task = Task::where('user_id', auth()->id())->with('status')->find($id);
        if (! $task) {
            return;
        }

        $next = $task->completed ? null : $this->nextStatus($task->status);
        if (! $next) {
            $this->toggleComplete($id, $gamification);

            return;
        }

        $task->update(['status_id' => $next->id]);
    }

    /** Primer estado del usuario del siguiente tipo abierto del flujo; null si toca completar. */
    private function nextStatus(?TaskStatus $status): ?TaskStatus
    {
        $types = [TaskStatus::TODO, TaskStatus::READY, TaskStatus::IN_PROGRESS];
        $position = array_search($status?->type ?? TaskStatus::TODO, $types, true);
        if ($position === false) {
            return null;
        }

        $this->statusCatalog ??= TaskStatus::query()->where('user_id', auth()->id())->ordered()->get();
        foreach (array_slice($types, $position + 1) as $type) {
            if ($next = $this->statusCatalog->firstWhere('type', $type)) {
                return $next;
            }
        }

        return null;
    }

    /** Etiqueta del botón principal de la fila según el siguiente paso. */
    public function advanceLabel(Task $task): string
    {
        if ($task->completed) {
            return 'Reabrir';
        }

        $next = $this->nextStatus($task->status);

        return $next ? 'Pasar a '.$next->name : 'Completar';
    }

    public function delete(string $id)
    {
        // Por modelo, no masivo: así corren el observer de CalDAV y la limpieza de vínculos.
        abort_unless(auth()->check(), 403);
        Task::where('user_id', auth()->id())->find($id)?->delete();
    }

}
