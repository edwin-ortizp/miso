<?php

namespace App\Mcp\Tools\Health;

use App\Actions\LogCycle;
use App\Mcp\Support\DateWindow;
use App\Mcp\Support\McpOutput;
use App\Models\CycleDayLog;
use App\Models\MenstrualCycle;
use App\Support\CycleProgress;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Ciclo menstrual del usuario: periodos, promedios y estimación del próximo inicio, más los registros diarios de un periodo (por defecto 60 días). Son datos íntimos y se registran como sensibles: sin include_sensitive=true NO devuelve datos, solo hidden_sensitive con cuántos hay. Pide include_sensitive=true únicamente si el usuario preguntó por su ciclo y no lo menciones fuera de ese tema. La estimación es informativa («tiende a»): no es un método anticonceptivo ni un diagnóstico.')]
class ListCycleTool extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            ...DateWindow::rules(),
            'days' => ['nullable', 'integer', 'min:1', 'max:366'],
            'include_sensitive' => ['nullable', 'boolean'],
        ]);
        $includeSensitive = (bool) ($data['include_sensitive'] ?? false);
        $window = DateWindow::fromInput($data, $data['days'] ?? 60);

        $cycleQuery = Auth::user()->menstrualCycles();
        $dayQuery = Auth::user()->cycleDayLogs()->whereDate('date', '>=', $window->fromDate())->whereDate('date', '<=', $window->toDate());

        if (! $includeSensitive) {
            $hidden = (clone $cycleQuery)->where('is_sensitive', true)->count() + (clone $dayQuery)->where('is_sensitive', true)->count();
            $cycleQuery->where('is_sensitive', false);
            $dayQuery->where('is_sensitive', false);
        }

        $cycles = $cycleQuery->orderByDesc('started_on')->get();
        $logs = $dayQuery->orderByDesc('date')->get();
        $summary = CycleProgress::summary($cycles);

        return Response::structured(McpOutput::compact([
            'hidden_sensitive' => ! $includeSensitive && ($hidden ?? 0) > 0 ? $hidden : null,
            'period' => $window->toArray(),
            'summary' => [
                'periods' => $summary['starts'],
                'average_cycle_days' => $summary['average_cycle'],
                'average_period_days' => $summary['average_period'],
                'cycle_day_today' => CycleProgress::cycleDay($cycles, LogCycle::today()),
                'period_in_progress' => $summary['open'],
            ],
            'estimate' => $summary['predicted_start'] ? [
                'next_start_between' => [$summary['window_from']->toDateString(), $summary['window_to']->toDateString()],
                'based_on_cycles' => $summary['intervals'],
                'note' => 'Estimación informativa («tiende a»); el ciclo puede variar. No es un método anticonceptivo ni un diagnóstico.',
            ] : ($cycles->isNotEmpty() ? ['note' => 'Hacen falta al menos '.CycleProgress::MIN_STARTS.' inicios registrados para estimar.'] : null),
            'periods' => $cycles->take(12)->map(fn (MenstrualCycle $c) => [
                'id' => $c->id,
                'started_on' => $c->started_on->toDateString(),
                'ended_on' => $c->ended_on?->toDateString(),
                'period_days' => $c->periodDays(),
            ])->values()->all(),
            'days' => $logs->take(90)->map(fn (CycleDayLog $l) => [
                'id' => $l->id,
                'date' => $l->date->toDateString(),
                'flow' => $l->flow,
                'symptoms' => $l->symptoms ?: null,
                'notes' => $l->notes,
            ])->values()->all(),
        ]));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'since' => $schema->string()->description('Registros diarios desde esta fecha (YYYY-MM-DD).'),
            'until' => $schema->string()->description('Hasta esta fecha (YYYY-MM-DD). Por defecto hoy.'),
            'days' => $schema->integer()->description('Días hacia atrás para los registros diarios (por defecto 60).'),
            'include_sensitive' => $schema->boolean()->description('Necesario para recibir datos del ciclo, que son sensibles. Úsalo solo si el usuario lo pidió.'),
        ];
    }
}
