<?php

namespace App\Mcp\Tools\Task;

use App\Models\Task;
use App\Services\Task\PlanningHours;
use App\Services\Task\TaskSlotFinder;
use App\Services\TaskCodeService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Encuentra huecos libres en la agenda del usuario donde cabe una tarea o una duración (solo lectura). Respeta el horario para planificar del usuario (por defecto lunes a viernes 8:00-18:00 y sábados 9:00-12:00) y trata como ocupado lo que tiene hora: tareas, comidas y tomas de medicamentos. Pasa task_id (id o código) para usar la duración estimada de la tarea y no contarla como ocupación, o minutes. Fechas y horas en hora de Bogotá (YYYY-MM-DD o YYYY-MM-DD HH:MM). Para agendar usa update-task-tool con start_date y end_date; no mueve tareas con horario fijo (schedule_locked).')]
class FindTaskSlotsTool extends Tool
{
    public function handle(Request $request, TaskSlotFinder $finder, PlanningHours $hours): ResponseFactory
    {
        $data = $request->validate([
            'task_id' => ['nullable', 'string', 'max:64'],
            'minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'part' => ['nullable', 'string', 'in:any,morning,afternoon'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $task = null;
        if (filled($data['task_id'] ?? null)) {
            $task = app(TaskCodeService::class)->resolve(Auth::id(), $data['task_id']);
            if (! $task) {
                return Response::make(Response::error('No se encontró la tarea o no te pertenece.'));
            }
        }

        $minutes = (int) ($data['minutes'] ?? 0);
        if ($minutes === 0) {
            $estimate = (int) ($task?->estimated_time ?? 0);
            $minutes = $estimate >= 5 && $estimate <= 480 ? $estimate : TaskSlotFinder::DEFAULT_MINUTES;
        }

        $tz = config('app.timezone');
        $from = filled($data['from'] ?? null) ? Carbon::parse($data['from'], $tz) : Carbon::today($tz);
        $to = filled($data['to'] ?? null) ? Carbon::parse($data['to'], $tz) : $from->copy()->addDays(6);

        try {
            $slots = $finder->find(Auth::id(), $minutes, $from, $to, [
                'part' => $data['part'] ?? 'any', 'limit' => $data['limit'] ?? 20, 'exclude_task_id' => $task?->id,
            ]);
        } catch (InvalidArgumentException $exception) {
            return Response::make(Response::error($exception->getMessage()));
        }

        $settings = $hours->settings(Auth::id());

        return Response::structured([
            'timezone' => $tz,
            'minutes' => $minutes,
            'task' => $task ? ['id' => $task->id, 'code' => $task->code, 'title' => $task->title, 'schedule_locked' => (bool) $task->schedule_locked] : null,
            'note' => $task?->schedule_locked
                ? 'Esta tarea tiene horario fijo: no la muevas salvo que el usuario lo pida y antes quítale schedule_locked.'
                : 'Son propuestas; agenda con update-task-tool solo después de que el usuario elija o confirme.',
            'planning_hours' => collect(PlanningHours::DAYS)->map(fn ($name, $day) => [
                'day' => $name,
                'enabled' => $settings['days'][$day]['enabled'],
                'start' => $settings['days'][$day]['start'],
                'end' => $settings['days'][$day]['end'],
            ])->values()->all(),
            'margin_minutes' => $settings['margin'],
            'slots' => array_map(fn (array $slot) => [
                'start' => $slot['start']->format('Y-m-d H:i'),
                'end' => $slot['end']->format('Y-m-d H:i'),
                'day' => $slot['start']->translatedFormat('l'),
            ], $slots),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->string()->description('Id o código (p. ej. SGX-00002) de la tarea a ubicar; usa su duración estimada y no cuenta como ocupación.'),
            'minutes' => $schema->integer()->description('Duración buscada en minutos (5-480). Por defecto la de la tarea o 60.'),
            'from' => $schema->string()->description('Primer día a revisar, YYYY-MM-DD (por defecto hoy).'),
            'to' => $schema->string()->description('Último día a revisar, YYYY-MM-DD (por defecto 6 días después; máximo 14 días de rango).'),
            'part' => $schema->string()->enum(['any', 'morning', 'afternoon'])->description('Momento del día: any (por defecto), morning (antes de las 12:00) o afternoon.'),
            'limit' => $schema->integer()->description('Máximo de huecos a devolver (por defecto 20, máximo 50).'),
        ];
    }
}
