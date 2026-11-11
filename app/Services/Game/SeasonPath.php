<?php

namespace App\Services\Game;

use App\Models\Game\GameCompanion;
use App\Models\Game\GameEvent;
use App\Models\Game\GameMission;
use App\Models\Game\GameSeason;
use App\Models\Game\GameSeasonNode;
use App\Models\Game\GameTeamMember;
use App\Models\Game\GameWeek;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Camino de temporada (CHG-079). Los puntos salen de lo hecho dentro de la temporada:
 * actividades del juego (1 punto cada una, hasta `daily_cap` por día y sin tareas creadas y
 * cerradas el mismo día) más 3 por misión semanal cumplida. Siete paradas con balls o xp para el
 * equipo, pagadas una sola vez (`game_season_nodes`). Solo la temporada activa avanza; al
 * terminar o revertirse el camino queda congelado con lo ganado.
 */
class SeasonPath
{
    public function __construct(
        private readonly Streak $streak,
        private readonly RewardGranter $granter,
        private readonly CompanionProgress $progress,
    ) {}

    /**
     * @return array{season_id: int, points: int, target: int, nodes: array<int, array{n: int, at: int, reward: array{type: string, count: int}, label: string, reached: bool, awarded: bool}>, next: ?array, frozen: bool}
     */
    public function state(int $userId, GameSeason $season): array
    {
        $frozen = $season->status !== GameSeason::ACTIVE || $season->ends_at->lte(now());
        $points = $this->points($userId, $season);
        $target = $this->target($season);
        $awarded = GameSeasonNode::withoutGlobalScopes()->where('user_id', $userId)->where('season_id', $season->id)->pluck('node')->all();

        $nodes = [];
        foreach ((array) config('game.season.path.nodes') as $i => $node) {
            $n = $i + 1;
            $nodes[] = [
                'n' => $n,
                'at' => (int) ceil($target * $node['percent'] / 100),
                'reward' => $node['reward'],
                'label' => self::rewardLabel($node['reward']),
                'reached' => in_array($n, $awarded, true),
                'awarded' => in_array($n, $awarded, true),
            ];
        }
        $next = collect($nodes)->first(fn (array $node) => ! $node['reached']);

        return ['season_id' => $season->id, 'points' => $points, 'target' => $target, 'nodes' => $nodes, 'next' => $next, 'frozen' => $frozen];
    }

    /** Entrega las paradas alcanzadas en la temporada activa, en orden y una sola vez. */
    public function awardPending(int $userId): void
    {
        $season = app(GameRules::class)->activeSeason($userId, now());
        if (! $season || ! app(GameSettings::class)->for($userId)['enabled']) {
            return;
        }

        try {
            $state = $this->state($userId, $season);
            foreach ($state['nodes'] as $node) {
                if ($node['awarded'] || $state['points'] < $node['at']) {
                    continue;
                }
                try {
                    GameSeasonNode::withoutGlobalScopes()->create([
                        'user_id' => $userId, 'season_id' => $season->id, 'node' => $node['n'],
                        'reward' => $node['reward'], 'awarded_at' => now(),
                    ]);
                } catch (QueryException) {
                    continue; // Otro proceso ya la entregó.
                }
                $this->reward($userId, $season, $node);
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function points(int $userId, GameSeason $season): int
    {
        $timezone = config('app.timezone');
        $from = CarbonImmutable::parse($season->starts_at)->timezone($timezone)->startOfDay();
        $end = CarbonImmutable::parse(min($season->ends_at, $season->reverted_at ?? $season->ends_at, now()))->timezone($timezone);
        if ($end->lt($from)) {
            return 0;
        }
        $cap = max(1, (int) config('game.season.path.daily_cap', 5));
        $start = CarbonImmutable::parse($season->starts_at);
        $activities = 0;
        foreach ($this->streak->dayStats($userId, $from, $end) as $date => $day) {
            // El primer día solo cuenta lo hecho desde que empezó la temporada.
            $count = $date === $from->toDateString()
                ? $this->countSince($userId, $start, $from->endOfDay(), $day['count'])
                : $day['count'];
            $activities += min($cap, $count);
        }

        $weekIds = GameWeek::withoutGlobalScopes()->where('user_id', $userId)
            ->whereDate('week_start', '>=', WeekPlanner::weekStart($season->starts_at)->toDateString())
            ->whereDate('week_start', '<', $end->toDateString())->pluck('id');
        $missions = GameMission::withoutGlobalScopes()->where('user_id', $userId)->whereIn('game_week_id', $weekIds)
            ->whereNotNull('full_rewarded_at')->where('full_rewarded_at', '>=', $season->starts_at)->where('full_rewarded_at', '<=', $end)
            ->count();

        return $activities + $missions * (int) config('game.season.path.mission_points', 3);
    }

    public function target(GameSeason $season): int
    {
        $weeks = max(1, (int) ceil(CarbonImmutable::parse($season->starts_at)->diffInDays($season->ends_at) / 7));

        return max((int) config('game.season.path.min_target', 20), $weeks * (int) config('game.season.path.points_per_week', 20));
    }

    /** @param array{type: string, count: int} $reward */
    public static function rewardLabel(array $reward): string
    {
        return $reward['type'] === 'xp'
            ? '+'.$reward['count'].' xp al equipo'
            : ($reward['count'] === 1 ? '1 ball' : $reward['count'].' balls');
    }

    private function countSince(int $userId, CarbonImmutable $start, CarbonImmutable $end, int $dayCount): int
    {
        $before = GameEvent::withoutGlobalScopes()->where('user_id', $userId)->where('kind', GameEvent::KIND_ACTIVITY)
            ->where('occurred_at', '>=', $start->startOfDay())->where('occurred_at', '<', $start)->count();

        return max(0, $dayCount - $before);
    }

    /** @param array{n: int, reward: array{type: string, count: int}, label: string} $node */
    private function reward(int $userId, GameSeason $season, array $node): void
    {
        $title = '¡Parada '.$node['n'].' del camino!';
        if ($node['reward']['type'] === 'ball') {
            for ($k = 1; $k <= $node['reward']['count']; $k++) {
                $this->granter->grantBonusBall($userId, "season:{$season->id}:node:{$node['n']}:{$k}", 'Camino · '.$season->name, [
                    'icon' => 'bi-signpost-2-fill', 'title' => $title, 'detail' => 'Ganaste una ball.', 'season_node' => $node['n'],
                ]);
            }

            return;
        }

        DB::transaction(function () use ($userId, $season, $node, $title): void {
            $key = "season:{$season->id}:node:{$node['n']}:xp";
            if (GameEvent::withoutGlobalScopes()->where('user_id', $userId)->where('idempotency_key', $key)->exists()) {
                return;
            }
            GameEvent::withoutGlobalScopes()->create([
                'user_id' => $userId, 'source_label' => 'Camino · '.$season->name, 'kind' => GameEvent::KIND_BONUS,
                'effect' => ['ball' => false, 'icon' => 'bi-signpost-2-fill', 'title' => $title, 'detail_no_ball' => $node['label'], 'season_node' => $node['n']],
                'idempotency_key' => $key, 'occurred_at' => now(),
            ]);
            $week = app(WeekPlanner::class)->forWeek($userId, now());
            $members = GameTeamMember::withoutGlobalScopes()->where('game_week_id', $week->id)->pluck('companion_id');
            $companions = GameCompanion::withoutGlobalScopes()->where('user_id', $userId)->whereIn('id', $members)->get();
            foreach ($companions as $companion) {
                $this->progress->addXp($companion, (int) $node['reward']['count'], ['type' => null, 'id' => null, 'label' => 'Camino de temporada'], "{$key}:{$companion->id}");
            }
        });
    }
}
