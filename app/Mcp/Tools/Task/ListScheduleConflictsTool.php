<?php

namespace App\Mcp\Tools\Task;

use App\Services\Task\TaskSlotFinder;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Lista los cruces de la agenda del usuario (solo lectura): grupos de bloques con hora que se solapan e incluyen al menos una tarea (con otras tareas, comidas o tomas de medicamentos). Cada bloque indica si se puede mover (movable): las tareas con horario fijo (schedule_locked, p. ej. reuniones), las comidas y las tomas no se mueven. Úsala cuando el usuario pregunte qué se le cruza o quiera reorganizar su agenda: primero esta herramienta, luego find-task-slots-tool para proponer huecos a las tareas movibles y, solo con la confirmación del usuario, update-task-tool. Horas en hora de Bogotá.')]
class ListScheduleConflictsTool extends Tool
{
    public function handle(Request $request, TaskSlotFinder $finder): ResponseFactory
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $tz = config('app.timezone');
        $from = filled($data['from'] ?? null) ? Carbon::parse($data['from'], $tz) : Carbon::today($tz);
        $to = filled($data['to'] ?? null) ? Carbon::parse($data['to'], $tz) : $from->copy()->addDays(6);

        try {
            $groups = $finder->conflicts(Auth::id(), $from, $to);
        } catch (InvalidArgumentException $exception) {
            return Response::make(Response::error($exception->getMessage()));
        }

        return Response::structured([
            'timezone' => $tz,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'conflicts' => array_map(fn (array $group) => [
                'start' => $group['start']->format('Y-m-d H:i'),
                'end' => $group['end']->format('Y-m-d H:i'),
                'items' => array_map(fn (array $item) => [
                    'source' => $item['source'],
                    'id' => $item['source'] === 'task' ? $item['id'] : null,
                    'title' => $item['title'],
                    'start' => $item['start']->format('Y-m-d H:i'),
                    'end' => $item['end']->format('Y-m-d H:i'),
                    'movable' => $item['movable'],
                ], $group['items']),
            ], $groups),
            'note' => $groups === [] ? 'No hay tareas que se crucen en este rango.' : 'Propón mover solo los bloques con movable=true y pide confirmación antes de escribir.',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()->description('Primer día a revisar, YYYY-MM-DD (por defecto hoy).'),
            'to' => $schema->string()->description('Último día a revisar, YYYY-MM-DD (por defecto 6 días después; máximo 14 días de rango).'),
        ];
    }
}
