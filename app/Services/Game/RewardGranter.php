<?php

namespace App\Services\Game;

use App\Models\Game\GameCompanion;
use App\Models\Game\GameEncounter;
use App\Models\Game\GameEvent;
use App\Models\Game\GameSpecies;
use App\Models\HabitCompletion;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Convierte una actividad elegible en un evento de juego y, si hay cupo, en una
 * ball pendiente. Es idempotente: la misma actividad el mismo día nunca da dos
 * balls. Nunca revoca nada: ninguna ausencia resta.
 */
class RewardGranter
{
    public function __construct(
        private readonly GameSettings $settings,
        private readonly EncounterRoller $roller,
    ) {}

    public function forTask(Task $task): ?GameEncounter
    {
        $at = $task->completed_at ?? now();

        // CHG-048: las tareas de categorías de constancia (Personal) dan micro-xp en vez de ball,
        // sin misiones ni cupo diario. Con la fuente apagada vuelven al flujo normal.
        if ($task->category && in_array($task->category, (array) config('game.consistency_task_categories'), true)) {
            $settings = $this->settings->for((int) $task->user_id);
            if ($settings['enabled'] && ($settings['sources']['personal_tasks'] ?? false)) {
                $this->safely(function () use ($task, $at) {
                    app(ConsistencyXp::class)->grant((int) $task->user_id, 'personal_tasks', $task, $at);

                    return null;
                });

                return null;
            }
        }

        return $this->safely(fn () => $this->grant(
            (int) $task->user_id, 'tasks', $task, (string) $task->title,
            'task:'.$task->getKey().':'.$at->copy()->timezone(config('app.timezone'))->toDateString(), $at,
            // task_id y series_id permiten reconocer la tarea en misiones concretas, también si es recurrente.
            ['category' => $task->category ?: null, 'task_id' => (string) $task->getKey(), 'series_id' => $task->recurrence_series_id ?: null],
        ));
    }

    public const HABIT_GROUP_LABELS = [
        'morning' => 'Hábitos de la mañana',
        'afternoon' => 'Hábitos de la tarde',
        'night' => 'Hábitos de la noche',
        'anytime' => 'Hábitos de cualquier momento',
    ];

    /** Una ball por momento del día completado, una sola vez por grupo y día. */
    public function forHabitGroup(HabitCompletion $completion, string $period): ?GameEncounter
    {
        $date = $completion->date instanceof CarbonInterface ? $completion->date->toDateString() : substr((string) $completion->date, 0, 10);
        $label = self::HABIT_GROUP_LABELS[$period] ?? self::HABIT_GROUP_LABELS['anytime'];

        return $this->safely(fn () => $this->grant(
            (int) $completion->user_id, 'habits', $completion, $label,
            'habit-group:'.$period.':'.$date, now(),
            ['category' => 'habits', 'period' => $period, 'date' => $date],
        ));
    }

    /**
     * @param  array{category?: ?string, period?: string, date?: string}  $context
     * @param  bool  $special  ball especial (p. ej. meta cumplida): no cuenta ni se limita por el cupo diario
     */
    public function grant(int $userId, string $source, Model $model, string $label, string $key, CarbonInterface $at, array $context = [], bool $special = false): ?GameEncounter
    {
        $settings = $this->settings->for($userId);
        if (! $settings['enabled'] || ! ($settings['sources'][$source] ?? false)) {
            return null;
        }
        // Capa de la temporada activa (CHG-022): pesos, línea destacada y su id en el snapshot.
        $settings = app(GameRules::class)->overlay($userId, $settings, $at);

        return DB::transaction(function () use ($userId, $source, $model, $label, $key, $at, $settings, $context, $special): ?GameEncounter {
            // Serializa por usuario: el cupo diario y la protección se calculan sin carreras.
            User::query()->whereKey($userId)->lockForUpdate()->first();

            if (GameEvent::withoutGlobalScopes()->where('user_id', $userId)->where('idempotency_key', $key)->exists()) {
                return null;
            }

            $event = GameEvent::withoutGlobalScopes()->create([
                'user_id' => $userId,
                'source_type' => $model->getMorphClass(),
                'source_id' => (string) $model->getKey(),
                'source_label' => mb_substr($label, 0, 255),
                'kind' => GameEvent::KIND_ACTIVITY,
                'config_snapshot' => $settings,
                'idempotency_key' => $key,
                'occurred_at' => $at,
                'effect' => ['source' => $source, 'ball' => false, 'context' => $context],
            ]);

            // Equipo y misiones de la semana (CHG-021): cuentan aunque se alcance el límite diario.
            // Un fallo aquí no debe impedir la ball.
            try {
                DB::transaction(fn () => app(ActivityEffects::class)->apply($event, $context));
            } catch (Throwable $exception) {
                Log::warning('game.effects_failed', ['event' => $event->id, 'error' => $exception->getMessage()]);
            }

            $timezone = config('app.timezone');
            $dayStart = now($timezone)->startOfDay();
            $grantedToday = GameEncounter::withoutGlobalScopes()->where('game_encounters.user_id', $userId)
                ->whereBetween('granted_at', [$dayStart, $dayStart->copy()->endOfDay()])
                ->whereHas('event', fn ($query) => $query->withoutGlobalScopes()->where('kind', GameEvent::KIND_ACTIVITY)->whereNull('effect->special'))
                ->count();
            if (! $special && $grantedToday >= $settings['daily_limit']) {
                $event->update(['effect' => ['source' => $source, 'ball' => false, 'reason' => 'daily_limit', 'context' => $context]]);

                return null;
            }

            $base = ['source' => $source, 'context' => $context];

            return $this->rollEncounter($userId, $event, $settings, $special ? $base + ['special' => true] : $base);
        });
    }

    /** Foco (CHG-027): una ball al cumplir la meta diaria de minutos de pomodoro. */
    public function forFocusDay(Model $session, int $userId, string $date): ?GameEncounter
    {
        return $this->safely(fn () => $this->grant($userId, 'focus', $session, 'Foco del día', "focus:{$date}", now(), ['category' => 'focus']));
    }

    /** Metas (CHG-027): una ball por meta y día al registrar progreso. */
    public function forGoalProgress(Model $entry, int $userId, int|string $goalId, string $goalTitle, string $date): ?GameEncounter
    {
        return $this->safely(fn () => $this->grant($userId, 'goals', $entry, 'Meta: '.$goalTitle, "goal-progress:{$goalId}:{$date}", now(), ['category' => 'goals']));
    }

    /** Meta cumplida (CHG-027): ball especial, una sola vez por meta, fuera del límite diario. */
    public function forGoalCompleted(Model $goal, int $userId, string $goalTitle): ?GameEncounter
    {
        return $this->safely(fn () => $this->grant($userId, 'goals', $goal, 'Meta cumplida: '.$goalTitle, 'goal-done:'.$goal->getKey(), now(), ['category' => 'goals'], true));
    }

    /** Salud (CHG-027): agua o ejercicio del día cumplido. $kind = water|exercise. */
    public function forHealthDay(Model $log, int $userId, string $kind, string $date): ?GameEncounter
    {
        $label = $kind === 'water' ? 'Agua del día' : 'Ejercicio del día';

        return $this->safely(fn () => $this->grant($userId, 'health', $log, $label, "{$kind}:{$date}", now(), ['category' => 'health']));
    }

    /**
     * Ball de misión semanal (CHG-021): no cuenta para el límite diario.
     * Idempotente por clave (`mission:{id}:{half|full}:{n}`).
     */
    public function grantMissionBall(int $userId, string $key, string $label, array $effect = []): ?GameEncounter
    {
        if (GameEvent::withoutGlobalScopes()->where('user_id', $userId)->where('idempotency_key', $key)->exists()) {
            return null;
        }

        $settings = app(GameRules::class)->overlay($userId, $this->settings->for($userId), now());
        $event = GameEvent::withoutGlobalScopes()->create([
            'user_id' => $userId,
            'source_label' => mb_substr($label, 0, 255),
            'kind' => GameEvent::KIND_MISSION_REWARD,
            'effect' => ['ball' => false] + $effect,
            'config_snapshot' => $settings,
            'idempotency_key' => $key,
            'occurred_at' => now(),
        ]);

        return $this->rollEncounter($userId, $event, $settings, $effect);
    }

    /** @param array<string, mixed> $baseEffect */
    private function rollEncounter(int $userId, GameEvent $event, array $settings, array $baseEffect): ?GameEncounter
    {
        $species = GameSpecies::query()->where('active', true)->where('stage', 1)->get();
        $knownLines = $this->knownLines($userId);
        $forceNew = $this->ballsWithoutNovelty($userId) >= $settings['pity_threshold'];
        $seed = random_int(1, PHP_INT_MAX >> 1);
        $picked = $this->roller->roll(
            $species, $knownLines, $settings['rarity_weights'], $forceNew, $seed,
            $settings['featured_line'] ?? null, (int) ($settings['featured_weight'] ?? 1),
            (array) ($settings['featured_types'] ?? []),
        );

        if (! $picked) {
            $event->update(['effect' => $baseEffect + ['ball' => false, 'reason' => 'empty_catalog']]);

            return null;
        }

        $isNew = ! in_array($picked->line_key, $knownLines, true);
        $event->update(['effect' => $baseEffect + ['ball' => true, 'new_line' => $isNew, 'forced_new' => $forceNew, 'season_id' => $settings['season_id'] ?? null]]);

        return GameEncounter::withoutGlobalScopes()->create([
            'user_id' => $userId,
            'game_event_id' => $event->id,
            'species_id' => $picked->id,
            'seed' => $seed,
            'granted_at' => now(),
        ]);
    }

    /** @return array<int, string> */
    private function knownLines(int $userId): array
    {
        $owned = GameCompanion::withoutGlobalScopes()->where('user_id', $userId)->pluck('line_key');
        $pending = GameEncounter::withoutGlobalScopes()->where('game_encounters.user_id', $userId)
            ->join('game_species', 'game_species.id', '=', 'game_encounters.species_id')
            ->pluck('game_species.line_key');

        return $owned->merge($pending)->unique()->values()->all();
    }

    /** Balls seguidas (las más recientes) que no trajeron una línea nueva. */
    private function ballsWithoutNovelty(int $userId): int
    {
        $count = 0;
        $events = GameEvent::withoutGlobalScopes()->where('user_id', $userId)
            ->whereIn('kind', [GameEvent::KIND_ACTIVITY, GameEvent::KIND_MISSION_REWARD])->orderByDesc('id')->limit(200)->get(['effect']);
        foreach ($events as $event) {
            if (! ($event->effect['ball'] ?? false)) {
                continue;
            }
            if ($event->effect['new_line'] ?? false) {
                break;
            }
            $count++;
        }

        return $count;
    }

    /** El juego nunca debe impedir completar una tarea o un hábito. */
    private function safely(callable $grant): ?GameEncounter
    {
        try {
            return $grant();
        } catch (QueryException $exception) {
            // Clave única: otro proceso ya registró esta actividad.
            if (str_contains(strtolower($exception->getMessage()), 'unique')) {
                return null;
            }
            Log::warning('game.grant_failed', ['error' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            Log::warning('game.grant_failed', ['error' => $exception->getMessage()]);
        }

        return null;
    }
}
