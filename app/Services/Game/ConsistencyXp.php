<?php

namespace App\Services\Game;

use App\Models\Game\GameCompanion;
use App\Models\Game\GameEvent;
use App\Models\Game\GameTeamMember;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Constancia (CHG-042): el primer registro del día de cada fuente (vehículo,
 * bienestar, personas, hogar) da `consistency_xp` a cada integrante del equipo
 * de la semana. No crea balls, no avanza misiones, sin afinidad ni bono de
 * temporada, y no consume el máximo diario. Solo suma.
 */
class ConsistencyXp
{
    public const LABEL_PREFIX = 'Constancia · ';

    public function __construct(
        private readonly GameSettings $settings,
        private readonly WeekPlanner $planner,
        private readonly CompanionProgress $progress,
    ) {}

    public function grant(int $userId, string $source, Model $model, CarbonInterface $at): void
    {
        $labels = (array) config('game.consistency_sources');
        if (! isset($labels[$source])) {
            return;
        }
        $settings = $this->settings->for($userId);
        if (! $settings['enabled'] || ! ($settings['sources'][$source] ?? false)) {
            return;
        }

        $date = $at->copy()->timezone('America/Bogota')->toDateString();
        $label = self::LABEL_PREFIX.$labels[$source];

        DB::transaction(function () use ($userId, $source, $model, $at, $date, $label): void {
            $activity = GameEvent::withoutGlobalScopes()->firstOrCreate(
                ['user_id' => $userId, 'idempotency_key' => "micro:{$source}:{$date}"],
                [
                    'source_type' => $model->getMorphClass(),
                    'source_id' => $model->getKey(),
                    'source_label' => $label,
                    'kind' => GameEvent::KIND_ACTIVITY,
                    'effect' => ['consistency' => true, 'source' => $source, 'date' => $date],
                    'occurred_at' => $at,
                ],
            );
            if (! $activity->wasRecentlyCreated) {
                return;
            }

            $week = $this->planner->forWeek($userId, $at);
            $xp = (int) config('game.consistency_xp');
            $members = GameTeamMember::withoutGlobalScopes()->where('game_week_id', $week->id)->orderBy('slot')->get();
            foreach ($members as $member) {
                $companion = GameCompanion::withoutGlobalScopes()->whereKey($member->companion_id)->lockForUpdate()->first();
                if (! $companion) {
                    continue;
                }
                $key = "team:{$activity->id}:{$companion->id}";
                $event = GameEvent::withoutGlobalScopes()->firstOrCreate(
                    ['user_id' => $userId, 'idempotency_key' => $key],
                    [
                        'source_type' => $activity->source_type,
                        'source_id' => $activity->source_id,
                        'source_label' => $label,
                        'kind' => GameEvent::KIND_XP_GAINED,
                        'effect' => ['companion_id' => $companion->id, 'xp' => $xp, 'team' => true, 'consistency' => true, 'activity_event_id' => $activity->id],
                        'occurred_at' => $at,
                    ],
                );
                if ($event->wasRecentlyCreated) {
                    $this->progress->addXp($companion, $xp, ['type' => $activity->source_type, 'id' => $activity->source_id, 'label' => $label], $key);
                }
            }
        });
    }
}
