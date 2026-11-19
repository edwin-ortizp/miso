<?php

namespace App\Livewire\Relationship\Concerns;

use App\Models\Task;
use App\Models\TaskAssociation;
use App\Services\TaskGamificationService;

/**
 * Tareas asociadas a una persona: crear, completar o reabrir y quitar el vínculo.
 * Las tareas siguen siendo del módulo Tareas; aquí solo se enlazan. Requiere ResolvesRelationship.
 */
trait ManagesRelationshipTasks
{
    #[\Livewire\Attributes\On('task-editor-saved')]
    public function refreshRelatedTasks(): void {}

    /** Completa o reabre una tarea vinculada. Las recurrentes se gestionan en Tareas. */
    public function toggleTask(string $id, TaskGamificationService $gamification): void
    {
        $task = $this->relationshipTask($id);

        if ($task->is_recurrent && ! $task->completed) {
            return;
        }

        $result = $gamification->toggle($task);

        if ($result['completed']) {
            $this->dispatch('task-completed', ...$result);
        }
    }

    /** Quita solo el vínculo: la tarea sigue en el módulo Tareas. */
    public function unlinkTask(string $id): void
    {
        TaskAssociation::unlink($this->relationshipTask($id), $this->relationship());
    }

    protected function relationshipTask(string $id): Task
    {
        return $this->relationship()->tasks()->where('tasks.id', $id)->firstOrFail();
    }

}
