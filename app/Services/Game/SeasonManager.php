<?php

namespace App\Services\Game;

use App\Models\Game\GameCompanion;
use App\Models\Game\GameEncounter;
use App\Models\Game\GameEvent;
use App\Models\Game\GameMission;
use App\Models\Game\GameSeason;
use App\Models\Game\GameSpecies;
use App\Models\Game\GameWeek;
use App\Models\TaskCategory;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Ciclo de vida de las temporadas del director. El director (schedule de Paseo)
 * solo llega aquí por MCP: lee `context()` y envía `propose()`. Todo resultado
 * pasa por SeasonValidator; una propuesta inválida nunca cambia el juego.
 */
class SeasonManager
{
    public function __construct(
        private readonly GameSettings $settings,
        private readonly SeasonValidator $validator,
        private readonly GameRules $rules,
    ) {}

    /** Resumen agregado y sin datos personales para el director. */
    public function context(int $userId): array
    {
        $now = CarbonImmutable::now();
        $rules = config('game.season');
        $settings = $this->settings->for($userId);
        $from = WeekPlanner::weekStart($now)->subWeeks($rules['history_weeks'] - 1);

        $weeks = [];
        for ($start = $from; $start <= $now; $start = $start->addWeek()) {
            $end = $start->addWeek();
            $activities = GameEvent::withoutGlobalScopes()->where('user_id', $userId)
                ->where('kind', GameEvent::KIND_ACTIVITY)->whereBetween('occurred_at', [$start, $end])->get(['effect']);
            $week = GameWeek::withoutGlobalScopes()->where('user_id', $userId)->whereDate('week_start', $start->toDateString())->first();
            $missions = $week ? GameMission::withoutGlobalScopes()->where('game_week_id', $week->id)->get() : collect();

            $scopes = [];
            foreach ($activities as $activity) {
                $scope = $activity->effect['context']['category'] ?? 'sin_categoria';
                $scopes[$scope] = ($scopes[$scope] ?? 0) + 1;
            }
            $weeks[] = [
                'week_start' => $start->toDateString(),
                'activities' => $activities->count(),
                'activities_by_scope' => $scopes,
                'balls' => GameEncounter::withoutGlobalScopes()->where('user_id', $userId)->whereBetween('granted_at', [$start, $end])->count(),
                'missions' => $missions->count(),
                'missions_half' => $missions->whereNotNull('half_rewarded_at')->count(),
                'missions_full' => $missions->whereNotNull('full_rewarded_at')->count(),
            ];
        }

        $active = $this->rules->activeSeason($userId, $now);
        $scheduled = GameSeason::withoutGlobalScopes()->where('user_id', $userId)->where('status', GameSeason::ACTIVE)
            ->where('starts_at', '>', $now)->orderBy('starts_at')->first();

        return [
            'director_enabled' => $settings['director_enabled'],
            'seasons_enabled' => $settings['seasons_enabled'],
            'proposal_due' => $this->isDue($userId, $now),
            'now' => $now->toIso8601String(),
            'categories' => TaskCategory::withoutGlobalScopes()->where('user_id', $userId)->pluck('name', 'key'),
            'pending_tasks_by_category' => app(MissionPendingTasks::class)->counts($userId),
            'collection' => [
                'species_total' => GameSpecies::query()->where('active', true)->count(),
                'companions' => GameCompanion::withoutGlobalScopes()->where('user_id', $userId)->count(),
                'lines_available' => GameSpecies::query()->where('active', true)->where('stage', 1)->count(),
            ],
            'weeks' => $weeks,
            'active_season' => $active ? $this->summary($active) : null,
            'scheduled_season' => $scheduled ? $this->summary($scheduled) : null,
            'recent_seasons' => GameSeason::withoutGlobalScopes()->where('user_id', $userId)->latest('id')->limit(6)->get()->map(fn ($s) => $this->summary($s))->all(),
            'limits' => $rules + ['mission_types' => ['specific_tasks', 'category_tasks', 'habit_groups', 'variety'], 'rarities' => config('game.rarities')],
            'base_rules' => [
                'rarity_weights' => $settings['rarity_weights'],
                'mission_types' => config('game.mission_types'),
                'mission_target' => config('game.mission_target'),
            ],
            // CHG-026 (aprobado por Alex): tareas abiertas no privadas, sin descripción, para misiones concretas.
            'open_tasks' => $this->openTasks($userId, $now),
            'scopes' => config('game.scopes'),
            'habit_periods' => \App\Models\HabitDefinition::withoutGlobalScopes()->where('user_id', $userId)->get(['time_of_day'])
                ->map(fn ($habit) => $habit->time_of_day ?: 'anytime')->unique()->values()->all(),
            'current_week' => $this->currentWeekSummary($userId, $now),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function openTasks(int $userId, CarbonImmutable $now): array
    {
        return \App\Models\Task::withoutGlobalScopes()->where('user_id', $userId)
            ->where('completed', false)->where('is_private', false)->where('is_recurrence_history', false)
            ->orderByRaw('CASE WHEN end_date IS NOT NULL AND end_date < ? THEN 0 ELSE 1 END', [$now])
            ->orderBy('created_at')
            ->limit(40)
            ->get(['id', 'code', 'title', 'category', 'end_date', 'size', 'priority', 'created_at', 'is_recurrent'])
            ->map(fn ($task) => [
                'id' => (string) $task->id,
                'code' => $task->code,
                'title' => $task->title,
                'category' => $task->category,
                'due' => $task->end_date?->toDateString(),
                'overdue' => $task->end_date !== null && $task->end_date->lt($now),
                'size' => $task->size,
                'priority' => $task->priority,
                'days_open' => (int) $task->created_at?->diffInDays($now),
                'recurrent' => (bool) $task->is_recurrent,
            ])->all();
    }

    private function currentWeekSummary(int $userId, CarbonImmutable $now): ?array
    {
        $week = GameWeek::withoutGlobalScopes()->where('user_id', $userId)->whereDate('week_start', WeekPlanner::weekStart($now)->toDateString())->first();
        if (! $week) {
            return ['week_start' => WeekPlanner::weekStart($now)->toDateString(), 'missions' => [], 'replaceable' => true, 'director_proposed' => false];
        }
        $missions = GameMission::withoutGlobalScopes()->where('game_week_id', $week->id)->get();
        $proposed = $missions->contains('source', GameMission::SOURCE_DIRECTOR);

        return [
            'week_start' => $week->week_start->toDateString(),
            'missions' => $missions->map(fn ($m) => ['id' => $m->id, 'type' => $m->type, 'title' => $m->title, 'params' => $m->params, 'target' => $m->target, 'progress' => $m->progress, 'source' => $m->source, 'editable' => $m->isEditable()])->all(),
            'replaceable' => ! $proposed && $this->missionsReplaceable($missions),
            'director_proposed' => $proposed,
        ];
    }

    private function directorProposed(int $userId, CarbonImmutable $now): bool
    {
        $week = GameWeek::withoutGlobalScopes()->where('user_id', $userId)->whereDate('week_start', WeekPlanner::weekStart($now)->toDateString())->first();

        return $week !== null && GameMission::withoutGlobalScopes()->where('game_week_id', $week->id)
            ->where('source', GameMission::SOURCE_DIRECTOR)->exists();
    }

    private function missionsReplaceable($missions): bool
    {
        return $missions->every(fn ($m) => $m->isEditable());
    }

    /** Sustituye solo las misiones indicadas, sin borrar identidad, aportes ni recompensas. */
    public function updateWeekMissions(int $userId, array $updates): array
    {
        return DB::transaction(function () use ($userId, $updates): array {
            User::query()->whereKey($userId)->lockForUpdate()->first();
            $reject = fn (string $message) => ['status' => 'rejected', 'message' => $message, 'errors' => [$message]];
            if (! $this->settings->for($userId)['director_enabled']) {
                return $reject('El director está en pausa.');
            }
            if (! array_is_list($updates) || $updates === [] || count($updates) > (int) config('game.missions_per_week')) {
                return $reject('Envía de 1 a '.config('game.missions_per_week').' sustituciones.');
            }
            $ids = [];
            $proposals = [];
            foreach ($updates as $update) {
                if (! is_array($update) || ! isset($update['id']) || ! is_int($update['id']) || $update['id'] < 1 || in_array($update['id'], $ids, true)) {
                    return $reject('Cada sustitución necesita un ID de misión entero, positivo y distinto.');
                }
                $ids[] = $update['id'];
                unset($update['id']);
                $proposals[] = $update;
            }
            $week = GameWeek::withoutGlobalScopes()->where('user_id', $userId)
                ->whereDate('week_start', WeekPlanner::weekStart(now())->toDateString())->first();
            if (! $week) {
                return $reject('No hay misiones para la semana actual.');
            }
            $missions = GameMission::withoutGlobalScopes()->where('user_id', $userId)->where('game_week_id', $week->id)
                ->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($missions->count() !== count($ids)) {
                return $reject('Las misiones deben pertenecer a tu semana actual.');
            }
            if (! $this->missionsReplaceable($missions)) {
                return $reject('Solo puedes cambiar misiones sin avance ni recompensas.');
            }
            $validated = app(MissionProposalValidator::class)->validate($proposals, $userId);
            if ($validated['errors'] !== []) {
                return ['status' => 'rejected', 'message' => 'Sustituciones rechazadas.', 'errors' => $validated['errors']];
            }
            $changed = 0;
            foreach ($ids as $index => $id) {
                $mission = $missions[$id];
                $mission->fill($validated['missions'][$index] + ['source' => GameMission::SOURCE_DIRECTOR]);
                if ($mission->isDirty()) {
                    $mission->save();
                    $changed++;
                }
            }

            return ['status' => $changed ? 'applied' : 'unchanged', 'message' => $changed.' misiones actualizadas.', 'errors' => []];
        });
    }

    /**
     * Misiones de la semana actual propuestas por el director (CHG-026). Solo
     * reemplaza si ninguna misión tiene avance: nunca se quita progreso.
     *
     * @param  array<int, mixed>  $missions
     * @return array{status: string, message: string, errors: array<int, string>}
     */
    public function proposeWeekMissions(int $userId, array $missions): array
    {
        return DB::transaction(function () use ($userId, $missions): array {
            User::query()->whereKey($userId)->lockForUpdate()->first();

            if (! $this->settings->for($userId)['director_enabled']) {
                return ['status' => 'rejected', 'message' => 'El director está en pausa.', 'errors' => ['El director está en pausa.']];
            }

            // CHG-028: una sola propuesta del director por semana. Un schedule que corre dos
            // veces o un reintento no cambian las misiones (el bloqueo de usuario evita carreras).
            if ($this->directorProposed($userId, CarbonImmutable::now())) {
                return ['status' => 'ya_propuestas', 'message' => 'Ya hay misiones del director para esta semana; se conservan.', 'errors' => []];
            }

            $result = app(MissionProposalValidator::class)->validate($missions, $userId);
            if ($result['errors'] !== []) {
                return ['status' => 'rejected', 'message' => 'Misiones rechazadas: '.implode(' ', $result['errors']), 'errors' => $result['errors']];
            }

            $week = app(WeekPlanner::class)->forWeek($userId, now());
            $current = GameMission::withoutGlobalScopes()->where('game_week_id', $week->id)->lockForUpdate()->get();
            if (! $this->missionsReplaceable($current)) {
                return ['status' => 'ya_con_avance', 'message' => 'Las misiones de esta semana ya tienen avance; se conservan.', 'errors' => []];
            }

            GameMission::withoutGlobalScopes()->whereIn('id', $current->pluck('id'))->delete();
            foreach ($result['missions'] as $mission) {
                GameMission::withoutGlobalScopes()->create([
                    'user_id' => $userId,
                    'game_week_id' => $week->id,
                    'source' => GameMission::SOURCE_DIRECTOR,
                ] + $mission);
            }

            return ['status' => 'applied', 'message' => count($result['missions']).' misiones aplicadas a la semana del '.$week->week_start->toDateString().'.', 'errors' => []];
        });
    }

    /**
     * @param  array<string, mixed>  $proposal
     * @return array{status: string, message: string, season: ?GameSeason, errors: array<int, string>}
     */
    public function propose(int $userId, array $proposal, string $source = 'mcp'): array
    {
        return DB::transaction(function () use ($userId, $proposal, $source): array {
            User::query()->whereKey($userId)->lockForUpdate()->first();
            $settings = $this->settings->for($userId);

            if (! $settings['director_enabled']) {
                return $this->rejected($userId, $proposal, $source, ['El director está en pausa.']);
            }

            $now = CarbonImmutable::now();
            if (! $this->isDue($userId, $now)) {
                return ['status' => 'not_due', 'message' => 'Aún no toca: la temporada actual sigue vigente.', 'season' => null, 'errors' => []];
            }

            $result = $this->validator->validate($proposal, $userId);
            if ($result['errors'] !== []) {
                return $this->rejected($userId, $proposal, $source, $result['errors']);
            }

            $current = $this->rules->activeSeason($userId, $now);
            $startsAt = $current?->ends_at ? CarbonImmutable::parse($current->ends_at) : $now;
            $endsAt = WeekPlanner::weekStart($startsAt)->addWeeks($result['config']['weeks']);
            if ($endsAt <= $startsAt) {
                $endsAt = $startsAt->addWeeks($result['config']['weeks']);
            }

            $season = GameSeason::withoutGlobalScopes()->create([
                'user_id' => $userId,
                'status' => GameSeason::ACTIVE,
                'name' => trim($proposal['name']),
                'narrative' => trim($proposal['narrative']),
                'notes' => $proposal['notes'] ?? null,
                'config' => $result['config'],
                'proposal' => $proposal,
                'source' => $source,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ]);

            return ['status' => 'active', 'message' => "Temporada «{$season->name}» activa del {$startsAt->toDateString()} al {$endsAt->toDateString()}.", 'season' => $season, 'errors' => []];
        });
    }

    /** Termina ahora la temporada (o cancela la programada). No toca nada ya ganado. */
    public function revert(int $userId, int $seasonId): GameSeason
    {
        return DB::transaction(function () use ($userId, $seasonId): GameSeason {
            $season = GameSeason::withoutGlobalScopes()->where('user_id', $userId)->whereKey($seasonId)
                ->where('status', GameSeason::ACTIVE)->lockForUpdate()->firstOrFail();
            $now = now();
            $season->update([
                'status' => GameSeason::REVERTED,
                'reverted_at' => $now,
                'ends_at' => $season->ends_at && $season->ends_at->lt($now) ? $season->ends_at : $now,
            ]);

            return $season;
        });
    }

    /** Toca proponer si no hay temporada vigente ni programada, o si la vigente termina pronto. */
    public function isDue(int $userId, CarbonImmutable $now): bool
    {
        $window = (int) config('game.season.propose_window_days');
        $pending = GameSeason::withoutGlobalScopes()->where('user_id', $userId)->where('status', GameSeason::ACTIVE)
            ->where('ends_at', '>', $now)->orderByDesc('ends_at')->first();

        if (! $pending) {
            return true;
        }
        if ($pending->starts_at->gt($now)) {
            return false; // Ya hay una programada.
        }

        return $pending->ends_at->lte($now->addDays($window));
    }

    /** @param array<string, mixed> $proposal @param array<int, string> $errors */
    private function rejected(int $userId, array $proposal, string $source, array $errors): array
    {
        $season = GameSeason::withoutGlobalScopes()->create([
            'user_id' => $userId,
            'status' => GameSeason::REJECTED,
            'name' => is_string($proposal['name'] ?? null) ? mb_substr($proposal['name'], 0, 60) : null,
            'proposal' => $proposal,
            'errors' => $errors,
            'source' => $source,
        ]);

        return ['status' => 'rejected', 'message' => 'Propuesta rechazada: '.implode(' ', $errors), 'season' => $season, 'errors' => $errors];
    }

    private function summary(GameSeason $season): array
    {
        return [
            'id' => $season->id,
            'name' => $season->name,
            'status' => $season->displayStatus(),
            'starts_at' => $season->starts_at?->toDateString(),
            'ends_at' => $season->ends_at?->toDateString(),
            'config' => $season->config,
            'errors' => $season->errors,
        ];
    }
}
