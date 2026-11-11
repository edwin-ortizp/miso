<?php

namespace App\Services\Game;

use App\Models\Game\GameEvent;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Racha de constancia (CHG-076). Un día se cumple con `streak_threshold` acciones del juego
 * (eventos `activity`) en al menos `game.streak.min_areas` áreas distintas. Si un día cerrado no
 * se cumple, se cubre con una gracia (2 por mes calendario) mientras haya; si no, la racha
 * vuelve a empezar y la mejor racha se conserva. El día en curso nunca rompe la racha.
 *
 * Las gracias se derivan al vuelo (no se guardan). Solo se escribe el resultado de cada día
 * cerrado en `user_active_days` (CHG-067) y, con `awardMilestones()`, las balls de los hitos.
 */
class Streak
{
    public const AREA_LABELS = [
        'tasks' => 'tareas', 'personal_tasks' => 'tareas', 'habits' => 'hábitos', 'focus' => 'foco',
        'goals' => 'metas', 'health' => 'salud', 'wellbeing' => 'bienestar', 'people' => 'personas',
        'home' => 'hogar', 'vehicle' => 'vehículo',
    ];

    public function __construct(
        private readonly GameSettings $settings,
        private readonly RewardGranter $granter,
    ) {}

    /**
     * Estado de la racha para mostrar. Null si el juego está apagado o la racha oculta.
     *
     * @return array{days: int, best: int, today: int, today_areas: int, goal: int, min_areas: int, met_today: bool, grace_left: int, grace_total: int, month: string, last7: array<int, array{date: string, status: string}>, broken: bool}|null
     */
    public function summary(int $userId, ?CarbonImmutable $today = null): ?array
    {
        $settings = $this->settings->for($userId);
        if (! $settings['enabled'] || ! $settings['streak_visible']) {
            return null;
        }

        try {
            return $this->compute($userId, (int) $settings['streak_threshold'], $today ?? CarbonImmutable::now(config('app.timezone'))->startOfDay());
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /** Paga una sola vez en la vida cada hito alcanzado por la racha actual. */
    public function awardMilestones(int $userId, array $summary): void
    {
        $reached = array_values(array_filter((array) config('game.streak.milestones'), fn (int $milestone) => $milestone <= $summary['days']));
        if ($reached === []) {
            return;
        }
        $paid = GameEvent::withoutGlobalScopes()->where('user_id', $userId)
            ->whereIn('idempotency_key', array_map(fn (int $milestone) => "streak:{$milestone}", $reached))
            ->pluck('idempotency_key')->all();
        $missing = array_filter($reached, fn (int $milestone) => ! in_array("streak:{$milestone}", $paid, true));
        if ($missing === []) {
            return;
        }

        $recap = $this->recap($userId, $summary['days']);
        foreach ($missing as $milestone) {
            $this->granter->grantBonusBall($userId, "streak:{$milestone}", "Racha de {$milestone} días", [
                'icon' => 'bi-fire',
                'title' => "¡{$milestone} días seguidos cuidándote!",
                'detail' => trim($recap.' Ganaste una ball.'),
                'detail_no_ball' => $recap !== '' ? $recap : null,
                'streak' => $milestone,
            ]);
        }
    }

    /** @return array{days: int, best: int, today: int, today_areas: int, goal: int, min_areas: int, met_today: bool, grace_left: int, grace_total: int, month: string, last7: array<int, array{date: string, status: string}>, broken: bool} */
    private function compute(int $userId, int $threshold, CarbonImmutable $today): array
    {
        $minAreas = max(1, (int) config('game.streak.min_areas', 2));
        $graceTotal = max(0, (int) config('game.streak.grace_days_per_month', 2));
        $yesterday = $today->subDay();
        $from = $today->subDays(max(7, (int) config('game.streak.history_days', 400)));

        $rows = DB::table('user_active_days')->where('user_id', $userId)
            ->whereBetween('date', [$from->toDateString(), $yesterday->toDateString()])
            ->orderBy('date')->get(['id', 'date', 'records_count', 'streak_met']);

        $met = [];
        $pending = [];
        foreach ($rows as $row) {
            $date = substr((string) $row->date, 0, 10);
            if ($row->streak_met !== null) {
                $met[$date] = (bool) $row->streak_met;
            } elseif ((int) $row->records_count >= $threshold) {
                $pending[$date] = (int) $row->id;
            } else {
                $met[$date] = false;
                DB::table('user_active_days')->where('id', $row->id)->update(['streak_met' => false, 'streak_threshold' => $threshold]);
            }
        }
        if ($pending !== []) {
            $stats = $this->dayStats($userId, CarbonImmutable::parse(array_key_first($pending)), CarbonImmutable::parse(array_key_last($pending)));
            foreach ($pending as $date => $id) {
                $day = $stats[$date] ?? ['count' => 0, 'areas' => 0];
                $met[$date] = $day['count'] >= $threshold && $day['areas'] >= $minAreas;
                DB::table('user_active_days')->where('id', $id)->update(['streak_met' => $met[$date], 'streak_threshold' => $threshold]);
            }
        }

        $todayStats = $this->dayStats($userId, $today, $today)[$today->toDateString()] ?? ['count' => 0, 'areas' => 0];
        $metToday = $todayStats['count'] >= $threshold && $todayStats['areas'] >= $minAreas;

        // Recorrido hacia adelante: la racha avanza con cada día cumplido; un día sin cumplir
        // consume una gracia del mes solo si hay racha que proteger.
        $run = 0;
        $best = 0;
        $graces = [];
        $status = [];
        $first = $rows->isEmpty() ? $today : CarbonImmutable::parse(substr((string) $rows->first()->date, 0, 10));
        for ($day = $first; $day->lt($today); $day = $day->addDay()) {
            $date = $day->toDateString();
            $month = $day->format('Y-m');
            if ($met[$date] ?? false) {
                $run++;
                $status[$date] = 'met';
            } elseif ($run > 0 && ($graces[$month] ?? 0) < $graceTotal) {
                $graces[$month] = ($graces[$month] ?? 0) + 1;
                $status[$date] = 'grace';
            } else {
                $run = 0;
                $status[$date] = 'none';
            }
            $best = max($best, $run);
        }
        $days = $run + ($metToday ? 1 : 0);
        $best = max($best, $days);

        $last7 = [];
        for ($day = $today->subDays(6); $day->lte($today); $day = $day->addDay()) {
            $date = $day->toDateString();
            $last7[] = ['date' => $date, 'status' => $day->eq($today) ? ($metToday ? 'met' : 'today') : ($status[$date] ?? 'none')];
        }

        return [
            'days' => $days,
            'best' => $best,
            'today' => $todayStats['count'],
            'today_areas' => $todayStats['areas'],
            'goal' => $threshold,
            'min_areas' => $minAreas,
            'met_today' => $metToday,
            'grace_left' => max(0, $graceTotal - ($graces[$today->format('Y-m')] ?? 0)),
            'grace_total' => $graceTotal,
            'month' => $today->locale('es')->translatedFormat('F'),
            'last7' => $last7,
            'broken' => $days === 0 && $best > 0,
        ];
    }

    /**
     * Acciones y áreas por día. Una tarea creada y cerrada el mismo día, sin fecha ni
     * recurrencia, no cuenta (antifarmeo, CHG-076 §2 criterio 11).
     *
     * @return array<string, array{count: int, areas: int}>
     */
    public function dayStats(int $userId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $timezone = config('app.timezone');
        $events = GameEvent::withoutGlobalScopes()->where('user_id', $userId)
            ->where('kind', GameEvent::KIND_ACTIVITY)
            ->whereBetween('occurred_at', [$from->startOfDay(), $to->endOfDay()])
            ->get(['source_type', 'source_id', 'effect', 'occurred_at']);

        $taskIds = $events->where('source_type', 'task')->pluck('source_id')->filter()->unique()->values();
        $tasks = $taskIds->isEmpty() ? collect() : Task::withoutGlobalScopes()->whereIn('id', $taskIds)
            ->get(['id', 'created_at', 'start_date', 'end_date', 'is_recurrent', 'recurrence_series_id'])->keyBy(fn (Task $task) => (string) $task->id);

        $days = [];
        foreach ($events as $event) {
            $date = CarbonImmutable::parse($event->occurred_at)->timezone($timezone)->toDateString();
            if ($event->source_type === 'task' && $this->isSameDayTask($tasks[(string) $event->source_id] ?? null, $date)) {
                continue;
            }
            $area = (string) ($event->effect['source'] ?? 'other');
            $area = $area === 'personal_tasks' ? 'tasks' : $area;
            $days[$date]['count'] = ($days[$date]['count'] ?? 0) + 1;
            $days[$date]['areas'][$area] = true;
        }

        return array_map(fn (array $day) => ['count' => $day['count'], 'areas' => count($day['areas'])], $days);
    }

    private function isSameDayTask(?Task $task, string $date): bool
    {
        if (! $task || $task->start_date || $task->end_date || $task->is_recurrent || $task->recurrence_series_id) {
            return false;
        }

        return $task->created_at && $task->created_at->copy()->timezone(config('app.timezone'))->toDateString() === $date;
    }

    /** "5 días con hábitos, 4 con tareas." — las dos áreas más constantes de la racha. */
    private function recap(int $userId, int $days): string
    {
        $from = CarbonImmutable::now(config('app.timezone'))->startOfDay()->subDays(max(0, $days - 1));
        $perArea = [];
        GameEvent::withoutGlobalScopes()->where('user_id', $userId)->where('kind', GameEvent::KIND_ACTIVITY)
            ->where('occurred_at', '>=', $from)->get(['effect', 'occurred_at'])
            ->each(function (GameEvent $event) use (&$perArea): void {
                $label = self::AREA_LABELS[$event->effect['source'] ?? ''] ?? null;
                if ($label) {
                    $perArea[$label][CarbonImmutable::parse($event->occurred_at)->timezone(config('app.timezone'))->toDateString()] = true;
                }
            });
        $perArea = array_map('count', $perArea);
        arsort($perArea);
        $top = array_slice($perArea, 0, 2, true);
        if ($top === []) {
            return '';
        }
        $parts = [];
        foreach ($top as $label => $count) {
            $parts[] = ($parts === [] ? "{$count} días con " : "{$count} con ").$label;
        }

        return implode(', ', $parts).'.';
    }
}
