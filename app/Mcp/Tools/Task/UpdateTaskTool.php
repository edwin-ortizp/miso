<?php

namespace App\Mcp\Tools\Task;

use App\Mcp\Tools\Task\Concerns\InteractsWithTaskFields;
use App\Services\TaskCodeService;
use App\Services\TaskRecurrenceService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Actualiza una tarea existente: título, descripción markdown (reemplazar o añadir al final), referencias externas (añadir o quitar), estado (borrador, ready, en curso; para terminar usa complete-task-tool), categoría, prioridad, tamaño, fechas, tiempo estimado, privacidad y recurrencia (asignarla, cambiarla o quitarla con recurrence=none). Solo se modifican los campos enviados; enviar null limpia el valor. Antes de reescribir la descripción, léela con get-task-tool.')]
class UpdateTaskTool extends Tool
{
    use InteractsWithTaskFields;

    public function handle(Request $request, TaskRecurrenceService $recurrence): Response
    {
        $data = $request->validate([
            'task_id' => ['required', 'string'],
            'code' => ['prohibited'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'append_description' => ['nullable', 'string'],
            'remove_external_refs' => ['nullable', 'array', 'max:20'],
            'remove_external_refs.*.provider' => ['required', 'string', 'max:40'],
            'remove_external_refs.*.type' => ['required', 'string', 'max:40'],
            'remove_external_refs.*.id' => ['required', 'string', 'max:255'],
            ...$this->fieldRules(),
        ]);

        $task = app(TaskCodeService::class)->resolve(Auth::id(), $data['task_id']);

        if (! $task) {
            return Response::error('No se encontró la tarea o no te pertenece.');
        }

        try {
            $attributes = $this->taskAttributes($data, $recurrence);
        } catch (InvalidArgumentException $exception) {
            return Response::error($exception->getMessage());
        }

        if (isset($data['title'])) {
            $attributes['title'] = trim($data['title']);
        }

        if (filled($data['append_description'] ?? null)) {
            $current = rtrim((string) (array_key_exists('description', $attributes) ? $attributes['description'] : $task->description));
            $attributes['description'] = ltrim($current."\n\n".trim($data['append_description']));
        }

        if (filled($data['add_external_refs'] ?? null) || filled($data['remove_external_refs'] ?? null)) {
            $attributes['external_refs'] = $task->mergeExternalRefs(
                $data['add_external_refs'] ?? [],
                $data['remove_external_refs'] ?? [],
            );
        }

        if ($attributes === []) {
            return Response::error('No enviaste ningún campo para actualizar.');
        }

        $task->update($attributes);
        $changed = array_values(array_diff(
            array_keys($task->getChanges()),
            ['updated_at', 'flow_position', 'start_is_date', 'end_is_date'],
        ));

        $changed = array_map(fn ($field) => $field === 'status_id' ? 'estado → '.$task->status?->name : $field, $changed);
        $message = "Tarea \"{$task->title}\" actualizada".($changed ? ' ('.implode(', ', $changed).')' : ' (sin cambios)').'.';
        if (array_intersect(['recurrence', 'is_recurrent'], $changed)) {
            $message .= $task->is_recurrent
                ? ' Ahora se repite '.$recurrence->describe($task->recurrence).'.'
                : ' Ya no es recurrente.';
        }

        return Response::text($message);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->string()
                ->description('UUID o código legible exacto de la tarea (p. ej. SGX-00002).')
                ->required(),
            'title' => $schema->string()
                ->description('Nuevo título.'),
            ...$this->fieldSchema($schema),
            'description' => $schema->string()
                ->description('Reemplaza toda la descripción markdown. Para agregar sin perder lo existente usa append_description.'),
            'append_description' => $schema->string()
                ->description('Markdown que se añade al final de la descripción actual (notas, subtareas "- [ ] ...").'),
            'remove_external_refs' => $this->externalRefsSchema($schema, 'Referencias externas a quitar (por provider + type + id).', false),
        ];
    }
}
