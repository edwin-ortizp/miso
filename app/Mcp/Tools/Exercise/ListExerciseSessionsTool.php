<?php

namespace App\Mcp\Tools\Exercise;

use App\Mcp\Support\DateWindow;
use App\Mcp\Support\McpOutput;
use App\Models\ExerciseLog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Sesiones de ejercicio del usuario en un periodo (por defecto los últimos 30 días) con totales de sesiones, minutos, distancia, calorías y pasos, también por tipo de ejercicio. Úsala cuando hable de entrenamiento, constancia o forma física, o para relacionar una molestia o lesión con lo que ha entrenado.')]
class ListExerciseSessionsTool extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            ...DateWindow::rules(),
            'days' => ['nullable', 'integer', 'min:1', 'max:366'],
            'exercise_type' => ['nullable', 'string'],
            'summary_only' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $window = DateWindow::fromInput($data, $data['days'] ?? 30);

        $sessions = Auth::user()->exerciseLogs()
            ->with('exerciseType')
            ->whereDate('date', '>=', $window->fromDate())
            ->whereDate('date', '<=', $window->toDate())
            ->when(filled($data['exercise_type'] ?? null), fn ($query) => $query->whereHas(
                'exerciseType',
                fn ($types) => $types->where('name', 'like', '%'.addcslashes(trim($data['exercise_type']), '%_').'%'),
            ))
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->get();

        $totals = fn ($group) => McpOutput::compact([
            'sessions' => $group->count(),
            'days' => $group->pluck('date')->map->toDateString()->unique()->count(),
            'minutes' => (int) $group->sum('duration') ?: null,
            'distance' => round((float) $group->sum('distance'), 2) ?: null,
            'calories' => (int) $group->sum('calories') ?: null,
            'steps' => (int) $group->sum('steps') ?: null,
        ]);

        return Response::structured(McpOutput::compact([
            'period' => $window->toArray(),
            'summary' => $totals($sessions),
            'by_type' => $sessions->groupBy(fn (ExerciseLog $log) => $log->exerciseType?->name ?? 'Sin tipo')
                ->map(fn ($group, $type) => ['type' => $type, ...$totals($group), 'last_on' => $group->first()->date->toDateString()])
                ->sortByDesc('sessions')->values()->all(),
            'last_session_on' => $sessions->first()?->date->toDateString(),
            'sessions' => ! empty($data['summary_only']) ? null : $sessions->take($data['limit'] ?? 50)->map(fn (ExerciseLog $log) => [
                'id' => $log->id,
                'date' => $log->date->toDateString(),
                'type' => $log->exerciseType?->name,
                'duration_min' => $log->duration,
                'distance' => $log->distance !== null ? (float) $log->distance : null,
                'total_reps' => $log->totalRepetitions(),
                'pace_seconds_per_km' => $log->paceSecondsPerKm(),
                'sets' => $log->sets,
                'reps' => $log->reps,
                'weight' => $log->weight !== null ? (float) $log->weight : null,
                'calories' => $log->calories,
                'calories_source' => $log->calories_source,
                'steps' => $log->steps,
                'notes' => $log->notes,
            ])->values()->all(),
        ]));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'since' => $schema->string()->description('Desde esta fecha (YYYY-MM-DD).'),
            'until' => $schema->string()->description('Hasta esta fecha (YYYY-MM-DD). Por defecto hoy.'),
            'days' => $schema->integer()->description('Días hacia atrás si no envías since (por defecto 30).'),
            'exercise_type' => $schema->string()->description('Filtra por tipo de ejercicio, p. ej. "Trotar".'),
            'summary_only' => $schema->boolean()->description('Devuelve solo totales, sin cada sesión.'),
            'limit' => $schema->integer()->description('Máximo de sesiones a listar (por defecto 50).'),
        ];
    }
}
