<?php

namespace App\Mcp\Tools\Focus;

use App\Mcp\Support\DateWindow;
use App\Mcp\Support\McpOutput;
use App\Support\PomodoroSettings;
use App\Models\PomodoroSession;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Tiempo de concentración (sesiones pomodoro) del usuario por día en un periodo (por defecto 7 días): minutos enfocados contra su meta diaria, en qué trabajó y totales. Úsala cuando hable de productividad, foco, horas trabajadas o cómo le rindió el día o la semana.')]
class ListFocusSessionsTool extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            ...DateWindow::rules(),
            'days' => ['nullable', 'integer', 'min:1', 'max:120'],
            'include_sessions' => ['nullable', 'boolean'],
        ]);

        $window = DateWindow::fromInput($data, $data['days'] ?? 7);
        $settings = app(PomodoroSettings::class)->for();
        $weekdayGoal = $settings['weekday_goal_minutes'];
        $weekendGoal = $settings['weekend_goal_minutes'];
        $timezone = config('app.timezone');

        $sessions = Auth::user()->pomodoroSessions()
            ->where('completed', true)
            ->whereDate('date', '>=', $window->fromDate())
            ->whereDate('date', '<=', $window->toDate())
            ->orderByDesc('date')
            ->get();

        $byDate = $sessions->groupBy(fn (PomodoroSession $session) => $session->date->toDateString());
        $days = collect();
        for ($day = $window->to->copy(); $day->gte($window->from); $day->subDay()) {
            $daySessions = $byDate->get($day->toDateString(), collect());
            $minutes = intdiv((int) $daySessions->sum('duration'), 60);
            $goal = $day->isWeekend() ? $weekendGoal : $weekdayGoal;
            $days->push(McpOutput::compact([
                'date' => $day->toDateString(),
                'minutes' => $minutes,
                'goal_minutes' => $goal,
                'goal_met' => $goal > 0 && $minutes >= $goal,
                'worked_on' => $daySessions->pluck('description')->filter()->unique()->take(6)->values()->all() ?: null,
            ]));
        }

        return Response::structured(McpOutput::compact([
            'period' => $window->toArray(),
            'summary' => [
                'sessions' => $sessions->count(),
                'hours' => round($sessions->sum('duration') / 3600, 1),
                'days_goal_met' => $days->where('goal_met', true)->count(),
                'average_minutes_per_day' => (int) round($days->avg('minutes')),
            ],
            'by_day' => $days->all(),
            'sessions' => empty($data['include_sessions']) ? null : $sessions->take(60)->map(fn (PomodoroSession $session) => McpOutput::compact([
                'id' => $session->id,
                'date' => $session->date->toDateString(),
                'start' => isset($session->start_time['timestamp']) ? Carbon::createFromTimestamp($session->start_time['timestamp'], $timezone)->format('H:i') : null,
                'end' => isset($session->end_time['timestamp']) ? Carbon::createFromTimestamp($session->end_time['timestamp'], $timezone)->format('H:i') : null,
                'minutes' => intdiv((int) $session->duration, 60),
                'description' => $session->description,
            ]))->values()->all(),
        ]));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'since' => $schema->string()->description('Desde esta fecha (YYYY-MM-DD).'),
            'until' => $schema->string()->description('Hasta esta fecha (YYYY-MM-DD). Por defecto hoy.'),
            'days' => $schema->integer()->description('Días hacia atrás si no envías since (por defecto 7).'),
            'include_sessions' => $schema->boolean()->description('Incluye cada sesión con hora y descripción.'),
        ];
    }
}
