<?php

namespace App\Mcp\Tools\Health;

use App\Mcp\Support\DateWindow;
use App\Mcp\Support\McpOutput;
use App\Models\SleepLog;
use App\Support\SleepProgress;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Sueño registrado por el usuario en un periodo (por defecto los últimos 14 días), con su meta de horas, promedio de duración, cuántos registros cumplieron la meta y calidad promedio. Úsala para «¿cómo he dormido?» o para cruzar el sueño con energía, ánimo o síntomas. No diagnostica. Los registros marcados como sensibles se omiten salvo include_sensitive=true; hidden_sensitive dice cuántos se ocultaron.')]
class ListSleepTool extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            ...DateWindow::rules(),
            'days' => ['nullable', 'integer', 'min:1', 'max:366'],
            'include_sensitive' => ['nullable', 'boolean'],
            'summary_only' => ['nullable', 'boolean'],
        ]);

        $window = DateWindow::fromInput($data, $data['days'] ?? 14);

        $query = Auth::user()->sleepLogs()
            ->whereDate('woke_at', '>=', $window->fromDate())
            ->whereDate('woke_at', '<=', $window->toDate());

        $includeSensitive = (bool) ($data['include_sensitive'] ?? false);
        $hidden = $includeSensitive ? 0 : (clone $query)->where('is_sensitive', true)->count();
        if (! $includeSensitive) {
            $query->where('is_sensitive', false);
        }

        $logs = $query->orderByDesc('woke_at')->get();
        $goal = SleepProgress::goalMinutes(Auth::user());
        $summary = SleepProgress::summary($logs, $goal);

        return Response::structured(McpOutput::compact([
            'period' => $window->toArray(),
            'hidden_sensitive' => $hidden ?: null,
            'goal_minutes' => $goal,
            'goal' => SleepLog::formatMinutes($goal),
            'summary' => [
                'records' => $summary['nights'],
                'average_minutes' => $summary['average_minutes'],
                'average' => $summary['average_minutes'] === null ? null : SleepLog::formatMinutes($summary['average_minutes']),
                'meeting_goal' => $summary['meeting_goal'],
                'average_quality' => $summary['average_quality'],
            ],
            'records' => ! empty($data['summary_only']) ? null : $logs->take(60)->map(fn (SleepLog $l) => [
                'id' => $l->id,
                'slept_at' => $l->slept_at->format('Y-m-d H:i'),
                'woke_at' => $l->woke_at->format('Y-m-d H:i'),
                'minutes' => $l->durationMinutes(),
                'duration' => $l->durationText(),
                'quality' => $l->quality,
                'meets_goal' => $l->durationMinutes() >= $goal,
                'notes' => $l->notes,
            ])->values()->all(),
        ]));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'since' => $schema->string()->description('Desde esta fecha de despertar (YYYY-MM-DD).'),
            'until' => $schema->string()->description('Hasta esta fecha (YYYY-MM-DD). Por defecto hoy.'),
            'days' => $schema->integer()->description('Días hacia atrás si no envías since (por defecto 14).'),
            'include_sensitive' => $schema->boolean()->description('Incluye los registros sensibles. Úsalo solo si el tema actual lo necesita.'),
            'summary_only' => $schema->boolean()->description('Devuelve solo el resumen, sin la lista.'),
        ];
    }
}
