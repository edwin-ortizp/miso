<?php

namespace App\Services\Game;

use App\Models\Game\GameCompanion;
use App\Models\Game\GameEvent;
use App\Models\Game\GameMission;
use App\Models\Game\GameTeamMember;
use App\Models\Task;
use Illuminate\Support\Facades\DB;

/**
 * Efectos semanales de una actividad elegible (CHG-021): xp para el equipo de
 * la semana (más a quien tiene afinidad) y avance de misiones. Solo suma.
 */
class ActivityEffects
{
    public function __construct(
        private readonly WeekPlanner $planner,
        private readonly CompanionProgress $progress,
    ) {}

    /** @param array{category?: ?string, period?: string, date?: string} $context */
    public function apply(GameEvent $activity, array $context): void
    {
        $week = $this->planner->forWeek((int) $activity->user_id, $activity->occurred_at);
        $this->teamXp($week->id, $activity, $context);
        $this->missions($week->id, $activity, $context);
    }

    private function teamXp(int $weekId, GameEvent $activity, array $context): void
    {
        $category = $context['category'] ?? null;
        $source = [
            'type' => $activity->source_type,
            'id' => $activity->source_id,
            'label' => 'Equipo de la semana · '.$activity->source_label,
        ];

        // Bono de ámbito de la temporada (CHG-022), acotado por el validador.
        $rules = app(GameRules::class)->for((int) $activity->user_id, $activity->occurred_at);
        $multiplier = $category !== null ? (float) ($rules['team_bonus'][$category] ?? 1.0) : 1.0;

        $members = GameTeamMember::withoutGlobalScopes()->where('game_week_id', $weekId)->orderBy('slot')->get();
        foreach ($members as $member) {
            $companion = GameCompanion::withoutGlobalScopes()->whereKey($member->companion_id)->lockForUpdate()->first();
            if (! $companion) {
                continue;
            }

            $affinity = $category !== null && $member->ability_key === $category;
            $xp = (int) round(((int) config('game.team_xp') + ($affinity ? (int) config('game.affinity_bonus_xp') : 0)) * $multiplier);
            $key = "team:{$activity->id}:{$companion->id}";

            $event = GameEvent::withoutGlobalScopes()->firstOrCreate(
                ['user_id' => $activity->user_id, 'idempotency_key' => $key],
                [
                    'source_type' => $source['type'],
                    'source_id' => $source['id'],
                    'source_label' => mb_substr($source['label'], 0, 255),
                    'kind' => GameEvent::KIND_XP_GAINED,
                    'effect' => ['companion_id' => $companion->id, 'xp' => $xp, 'team' => true, 'affinity' => $affinity, 'multiplier' => $multiplier, 'season_id' => $rules['season_id'], 'activity_event_id' => $activity->id],
                    'occurred_at' => $activity->occurred_at,
                ],
            );
            if ($event->wasRecentlyCreated) {
                $this->progress->addXp($companion, $xp, $source, $key);
            }
        }
    }

    private function missions(int $weekId, GameEvent $activity, array $context): void
    {
        $category = $context['category'] ?? null;
        $missions = GameMission::withoutGlobalScopes()->where('game_week_id', $weekId)->lockForUpdate()->get();

        foreach ($missions as $mission) {
            $contribution = match ($mission->type) {
                GameMission::TYPE_CATEGORY => $category !== null && $category === ($mission->params['category'] ?? null) ? "activity:{$activity->id}" : null,
                GameMission::TYPE_HABITS => ($context['period'] ?? null) === ($mission->params['period'] ?? null) && isset($context['date']) ? "day:{$context['date']}" : null,
                GameMission::TYPE_VARIETY => $category !== null ? "scope:{$category}" : null,
                GameMission::TYPE_SPECIFIC => $this->specificTaskKey($mission, $context),
                default => null,
            };
            if ($contribution === null) {
                continue;
            }

            $inserted = DB::table('game_mission_contributions')->insertOrIgnore([
                'game_mission_id' => $mission->id,
                'contribution_key' => $contribution,
                'game_event_id' => $activity->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            if ($inserted === 0 || $mission->progress >= $mission->target) {
                continue;
            }

            $mission->progress = min($mission->target, $mission->progress + 1);
            $mission->save();
            $this->reward($mission);
        }
    }

    /**
     * Misión de tareas concretas (CHG-026): cuenta una vez por tarea. Una tarea
     * recurrente se reconoce por su serie (cada ocurrencia completada es otra fila).
     */
    private function specificTaskKey(GameMission $mission, array $context): ?string
    {
        $taskId = $context['task_id'] ?? null;
        if ($taskId === null) {
            return null;
        }
        $seriesId = $context['series_id'] ?? null;

        foreach ($mission->params['tasks'] ?? [] as $task) {
            // Una recurrente recibe su serie en la primera ocurrencia: si no estaba guardada, se consulta la actual.
            $taskSeries = $task['series_id'] ?? null;
            if ($taskSeries === null && $seriesId !== null) {
                $taskSeries = Task::withoutGlobalScopes()->whereKey($task['id'])->value('recurrence_series_id');
            }
            $matches = $task['id'] === $taskId || ($seriesId !== null && $taskSeries === $seriesId);
            if ($matches) {
                return 'task:'.$task['id'];
            }
        }

        return null;
    }

    private function reward(GameMission $mission): void
    {
        $granter = app(RewardGranter::class);
        $rewards = config('game.mission_rewards');

        if (! $mission->half_rewarded_at && $mission->progress >= $mission->halfThreshold() && $mission->progress < $mission->target) {
            for ($n = 1; $n <= (int) $rewards['half']; $n++) {
                $granter->grantMissionBall($mission->user_id, "mission:{$mission->id}:half:{$n}", "Misión: {$mission->title}", ['mission_id' => $mission->id, 'stage' => 'half']);
            }
            $mission->half_rewarded_at = now();
            $mission->save();
        }

        if (! $mission->full_rewarded_at && $mission->progress >= $mission->target) {
            // Si la misión se completa de golpe, también recibe la de mitad.
            if (! $mission->half_rewarded_at) {
                for ($n = 1; $n <= (int) $rewards['half']; $n++) {
                    $granter->grantMissionBall($mission->user_id, "mission:{$mission->id}:half:{$n}", "Misión: {$mission->title}", ['mission_id' => $mission->id, 'stage' => 'half']);
                }
                $mission->half_rewarded_at = now();
            }
            for ($n = 1; $n <= (int) $rewards['full']; $n++) {
                $granter->grantMissionBall($mission->user_id, "mission:{$mission->id}:full:{$n}", "Misión: {$mission->title}", ['mission_id' => $mission->id, 'stage' => 'full']);
            }
            $mission->full_rewarded_at = now();
            $mission->save();
        }
    }
}
