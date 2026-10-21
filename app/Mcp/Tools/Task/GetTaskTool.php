<?php

namespace App\Mcp\Tools\Task;

use App\Models\Task;
use App\Services\TaskCodeService;
use App\Services\TaskRecurrenceService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Devuelve el detalle completo de una tarea: estado (nombre y tipo todo/ready/in_progress/done), descripción en markdown, progreso de subtareas, referencias externas, categoría, fechas, estimación y recurrencia con la próxima fecha sugerida. Úsala para leer el contenido antes de recomendar, editar o completar una tarea recurrente.')]
class GetTaskTool extends Tool
{
    public function handle(Request $request, TaskRecurrenceService $recurrence): Response|ResponseFactory
    {
        $data = $request->validate([
            'task_id' => ['required', 'string'],
            'code' => ['prohibited'],
        ]);

        $task = app(TaskCodeService::class)->resolve(Auth::id(), $data['task_id']);

        if (! $task) {
            return Response::error('No se encontró la tarea o no te pertenece.');
        }

        $category = $task->category ? Auth::user()->taskCategories()->where('key', $task->category)->first() : null;

        return Response::structured([
            'id' => $task->id,
            'code' => $task->code,
            'title' => $task->title,
            'description' => $task->description,
            'subtasks' => $task->subtask_progress,
            'external_refs' => $task->external_refs ?? [],
            'category' => $task->category,
            'category_name' => $category?->name,
            'priority' => $task->priority,
            'size' => $task->size,
            'estimated_minutes' => $task->estimated_time,
            'status' => $task->status ? ['id' => $task->status->id, 'name' => $task->status->name, 'type' => $task->status->type] : null,
            'completed' => $task->completed,
            'completed_at' => $task->completed_at?->toIso8601String(),
            'start_date' => $task->start_date?->format($task->start_is_date ? 'Y-m-d' : 'Y-m-d H:i'),
            'end_date' => $task->end_date?->format($task->end_is_date ? 'Y-m-d' : 'Y-m-d H:i'),
            'is_private' => $task->is_private,
            'is_recurrent' => $task->is_recurrent,
            'recurrence' => $task->is_recurrent ? [
                'summary' => $recurrence->describe($task->recurrence),
                'rule' => $task->recurrence,
                'suggested_next_date' => $task->completed ? null : $recurrence->suggestedNextDate($task)->toDateString(),
            ] : null,
            'linked' => $this->linked($task),
            'created_at' => $task->created_at?->toIso8601String(),
            'updated_at' => $task->updated_at?->toIso8601String(),
        ]);
    }

    /** Personas, eventos de salud y metas vinculados (link-task-tool). */
    private function linked(Task $task): ?array
    {
        $task->loadMissing(['relationships', 'healthEvents', 'goals']);

        $linked = array_filter([
            'contacts' => $task->relationships->map(fn ($person) => ['id' => $person->id, 'name' => $person->displayName()])->all(),
            'health_events' => $task->healthEvents->map(fn ($event) => ['id' => $event->id, 'title' => $event->title, 'date' => $event->event_date->toDateString()])->all(),
            'goals' => $task->goals->map(fn ($goal) => ['id' => $goal->id, 'title' => $goal->title])->all(),
        ]);

        return $linked ?: null;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->string()
                ->description('UUID o código legible exacto de la tarea (p. ej. SGX-00002).')
                ->required(),
        ];
    }
}
