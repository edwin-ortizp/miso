<?php

namespace App\Mcp\Tools\Task;

use App\Mcp\Tools\Relationship\Concerns\ResolvesContact;
use App\Models\TaskAssociation;
use App\Services\TaskCodeService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Vincula (o desvincula) una tarea con una persona, un evento de salud o una meta, para que aparezca en el contexto de esa persona o de ese malestar. Úsala al crear tareas que nacen de una cita médica ("pedir cita para la ecografía"), de alguien ("comprarle el regalo a Ali") o de una meta.')]
class LinkTaskTool extends Tool
{
    use ResolvesContact;

    private const TARGETS = ['contact', 'health_event', 'goal'];

    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'task_id' => ['required', 'string'],
            'code' => ['prohibited'],
            'target_type' => ['required', 'string', Rule::in(self::TARGETS)],
            'target_id' => ['nullable', 'string'],
            'contact_name' => ['nullable', 'string'],
            'unlink' => ['nullable', 'boolean'],
        ]);

        $task = app(TaskCodeService::class)->resolve(Auth::id(), $data['task_id']);
        if (! $task) {
            return Response::error('No se encontró la tarea o no te pertenece.');
        }

        $target = match ($data['target_type']) {
            'contact' => $this->resolveContact($data['target_id'] ?? null, $data['contact_name'] ?? null),
            'health_event' => Auth::user()->healthEvents()->find($data['target_id'] ?? '')
                ?? Response::error('No se encontró el evento de salud. Pasa su id en target_id (búscalo con list-health-events-tool).'),
            'goal' => Auth::user()->goals()->find($data['target_id'] ?? '')
                ?? Response::error('No se encontró la meta. Pasa su id en target_id.'),
        };

        if ($target instanceof Response) {
            return $target;
        }

        $label = match ($data['target_type']) {
            'contact' => $target->displayName(),
            'health_event' => "\"{$target->title}\"",
            'goal' => "\"{$target->title}\"",
        };

        if (! empty($data['unlink'])) {
            $removed = TaskAssociation::unlink($task, $target);

            return Response::text($removed > 0
                ? "Tarea \"{$task->title}\" desvinculada de {$label}."
                : "La tarea \"{$task->title}\" no estaba vinculada a {$label}.");
        }

        TaskAssociation::link($task, $target);

        return Response::text("Tarea \"{$task->title}\" vinculada a {$label}.");
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->string()->description('UUID o código legible exacto de la tarea.')->required(),
            'target_type' => $schema->string()->enum(self::TARGETS)->description('Qué se vincula: contact (persona), health_event o goal.')->required(),
            'target_id' => $schema->string()->description('Id del destino. Para contact puede usarse contact_name en su lugar.'),
            'contact_name' => $schema->string()->description('Nombre, apodo o alias de la persona (solo para target_type=contact).'),
            'unlink' => $schema->boolean()->description('true para quitar el vínculo en lugar de crearlo.'),
        ];
    }
}
