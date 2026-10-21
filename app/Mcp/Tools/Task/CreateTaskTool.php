<?php

namespace App\Mcp\Tools\Task;

use App\Mcp\Tools\Task\Concerns\InteractsWithTaskFields;
use App\Models\Task;
use App\Services\TaskRecurrenceService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Crea una nueva tarea para el usuario autenticado, opcionalmente recurrente. Si el usuario no indicó la categoría, pregúntale cuál usar mostrándole las opciones de list-task-categories-tool (o propón la más adecuada) antes de crearla.')]
class CreateTaskTool extends Tool
{
    use InteractsWithTaskFields;

    public function handle(Request $request, TaskRecurrenceService $recurrence): Response
    {
        $data = $request->validate([
            'code' => ['prohibited'],
            'title' => ['required', 'string', 'max:255'],
            ...$this->fieldRules(),
        ]);

        try {
            $attributes = $this->taskAttributes($data, $recurrence);
        } catch (InvalidArgumentException $exception) {
            return Response::error($exception->getMessage());
        }

        /** @var Task $task */
        $task = Auth::user()->tasks()->create([
            'task_code' => random_int(10000, 99999),
            'title' => trim($data['title']),
            'is_private' => false,
            ...$attributes,
            'external_refs' => (new Task)->mergeExternalRefs($data['add_external_refs'] ?? []),
        ]);

        $message = "Tarea creada: \"{$task->title}\" (code: {$task->code}, id: {$task->id}) en estado \"{$task->status?->name}\".";
        if (! $task->category) {
            $message .= ' Quedó sin categoría.';
        }
        if ($task->is_recurrent) {
            $message .= ' Se repite '.$recurrence->describe($task->recurrence).'.';
        }

        return Response::text($message);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()
                ->description('Título de la tarea.')
                ->required(),
            ...$this->fieldSchema($schema),
        ];
    }
}
