<?php

namespace App\Mcp\Tools\Task;

use App\Services\TaskCodeService;
use App\Services\TaskGamificationService;
use App\Services\TaskRecurrenceService;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Marca una tarea del usuario autenticado como completada o pendiente. Si la tarea es recurrente se programa la siguiente ocurrencia: si el usuario no indicó cuándo, consulta get-task-tool para ver la fecha sugerida y pregúntale si le sirve o prefiere otra antes de completarla; luego envíala en next_occurrence_date.')]
class CompleteTaskTool extends Tool
{
    public function handle(Request $request, TaskGamificationService $gamification, TaskRecurrenceService $recurrence): Response
    {
        $data = $request->validate([
            'task_id' => ['required', 'string'],
            'code' => ['prohibited'],
            'action' => ['nullable', 'string', Rule::in(['complete', 'reopen', 'toggle'])],
            'next_occurrence_date' => ['nullable', 'date'],
        ]);

        $task = app(TaskCodeService::class)->resolve(Auth::id(), $data['task_id']);

        if (! $task) {
            return Response::error('No se encontró la tarea o no te pertenece.');
        }

        $willComplete = match ($data['action'] ?? 'toggle') {
            'complete' => true,
            'reopen' => false,
            default => ! $task->completed,
        };

        if (! $willComplete) {
            $result = $gamification->reopen($task);
        } elseif ($task->is_recurrent) {
            $nextDate = isset($data['next_occurrence_date']) ? Carbon::parse($data['next_occurrence_date']) : null;

            $result = $nextDate
                ? $recurrence->completeAndSchedule($task, $nextDate, $gamification)
                : $recurrence->completeAndScheduleAutomatically($task, $gamification);

            // completeAt() mutates its own lockForUpdate() copy of the task, not this instance.
            $task = Auth::user()->tasks()->find($task->id);
        } else {
            $result = $gamification->complete($task);
        }

        if (! $result['completed']) {
            return Response::text("Tarea \"{$task->title}\" marcada como pendiente.");
        }

        $xpMessage = isset($result['xp']) ? " (+{$result['xp']} XP)" : '';
        $message = "Tarea \"{$task->title}\" completada{$xpMessage}.";

        if ($task->is_recurrent) {
            $message .= $task->completed
                ? ' Era la última ocurrencia: la serie recurrente ha finalizado.'
                : ' Próxima ocurrencia: '.($task->start_date?->toDateString() ?? $task->end_date?->toDateString()).'.';
        }

        return Response::text($message);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->string()
                ->description('UUID o código legible exacto de la tarea (p. ej. SGX-00002).')
                ->required(),
            'action' => $schema->string()
                ->enum(['complete', 'reopen', 'toggle'])
                ->description('Acción a realizar. Por defecto alterna el estado actual.'),
            'next_occurrence_date' => $schema->string()
                ->description('Fecha (YYYY-MM-DD) para la siguiente ocurrencia si la tarea es recurrente. Si se omite, se calcula automáticamente según el patrón de recurrencia. Se ignora si la tarea no es recurrente o si la acción no completa la tarea.'),
        ];
    }
}
