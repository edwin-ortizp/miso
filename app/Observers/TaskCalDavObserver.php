<?php

namespace App\Observers;

use App\Models\CalDavChange;
use App\Models\Task;

class TaskCalDavObserver
{
    public function created(Task $task): void
    {
        $this->record($task, 'created');
    }

    /** CHG-095: la revisión viaja en el mismo UPDATE del guardado (antes era un segundo UPDATE). */
    public function updating(Task $task): void
    {
        if (! $task->is_recurrence_history) {
            $task->forceFill(['caldav_revision' => max(1, (int) $task->caldav_revision + 1)]);
        }
    }

    public function updated(Task $task): void
    {
        $this->record($task, 'modified');
    }

    public function deleted(Task $task): void
    {
        $this->record($task, 'deleted');
    }

    private function record(Task $task, string $operation): void
    {
        if ($task->is_recurrence_history || ! $task->user_id) {
            return;
        }

        CalDavChange::create([
            'user_id' => $task->user_id,
            'task_id' => $operation === 'deleted' ? null : $task->id,
            'uri' => $task->caldav_uri ?: $task->id.'.ics',
            'operation' => $operation,
        ]);
    }
}
