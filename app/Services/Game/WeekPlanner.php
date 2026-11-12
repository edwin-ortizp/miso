<?php

namespace App\Services\Game;

use App\Models\Game\GameCompanion;
use App\Models\Game\GameMission;
use App\Models\Game\GameTeamMember;
use App\Models\Game\GameWeek;
use App\Models\HabitDefinition;
use App\Models\TaskCategory;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Arma la semana de juego de forma perezosa (sin scheduler): la primera vez que
 * se consulta o que llega una actividad de esa semana. Es idempotente y la
 * aleatoriedad usa una semilla registrada. Si la semana empezó con huecos en el
 * equipo, los completa cuando hay nuevos ejemplares.
 */
class WeekPlanner
{
    public const HABIT_PERIOD_LABELS = [
        'morning' => 'la mañana',
        'afternoon' => 'la tarde',
        'night' => 'la noche',
        'anytime' => 'cualquier momento',
    ];

    public function __construct(private readonly GameSettings $settings) {}

    public static function weekStart(CarbonInterface $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date)->timezone(config('app.timezone'))->startOfWeek(CarbonInterface::MONDAY)->startOfDay();
    }

    public function forWeek(int $userId, CarbonInterface $date): GameWeek
    {
        $start = self::weekStart($date)->toDateString();

        // CHG-095: leer no bloquea. Solo se abre la transacción con candado cuando hay
        // algo que crear (la semana o un hueco del equipo que sí se puede llenar).
        $existing = GameWeek::withoutGlobalScopes()->where('user_id', $userId)->whereDate('week_start', $start)->first();
        if ($existing && $this->teamIsSettled($existing)) {
            return $existing;
        }

        return DB::transaction(function () use ($userId, $start): GameWeek {
            User::query()->whereKey($userId)->lockForUpdate()->first();

            $week = GameWeek::withoutGlobalScopes()->where('user_id', $userId)->whereDate('week_start', $start)->first();
            if (! $week) {
                $week = GameWeek::withoutGlobalScopes()->create([
                    'user_id' => $userId,
                    'week_start' => $start,
                    'seed' => random_int(1, PHP_INT_MAX >> 1),
                    'config_snapshot' => $this->settings->for($userId) + [
                        'team_size' => (int) config('game.team_size'),
                        'team_xp' => (int) config('game.team_xp'),
                        'affinity_bonus_xp' => (int) config('game.affinity_bonus_xp'),
                    ],
                ]);
                $this->createMissions($week);
            }

            $this->fillTeam($week);

            return $week;
        });
    }

    /** Equipo completo o sin ejemplares para completarlo; deja los integrantes cargados en `members`. */
    private function teamIsSettled(GameWeek $week): bool
    {
        $members = GameTeamMember::withoutGlobalScopes()->where('game_week_id', $week->id)->orderBy('slot')->get();
        $week->setRelation('members', $members);
        if ($members->count() >= (int) config('game.team_size')) {
            return true;
        }

        return ! GameCompanion::withoutGlobalScopes()->where('user_id', $week->user_id)
            ->whereNotIn('id', $members->pluck('companion_id'))->exists();
    }

    private function fillTeam(GameWeek $week): void
    {
        $size = (int) config('game.team_size');
        $members = GameTeamMember::withoutGlobalScopes()->where('game_week_id', $week->id)->get();
        if ($members->count() >= $size) {
            return;
        }

        $candidates = GameCompanion::withoutGlobalScopes()->where('user_id', $week->user_id)
            ->whereNotIn('id', $members->pluck('companion_id'))->orderBy('id')->get();
        if ($candidates->isEmpty()) {
            return;
        }

        // Rotación: menos apariciones previas = más probabilidad.
        $appearances = GameTeamMember::withoutGlobalScopes()->where('user_id', $week->user_id)
            ->where('game_week_id', '!=', $week->id)
            ->selectRaw('companion_id, count(*) as total')->groupBy('companion_id')->pluck('total', 'companion_id');
        $abilities = $this->abilities($week->user_id);

        mt_srand($week->seed + $members->count());
        try {
            $slot = (int) $members->max('slot');
            foreach ($this->weightedSample($candidates, $appearances, $size - $members->count()) as $companion) {
                $ability = $abilities[mt_rand(0, count($abilities) - 1)];
                GameTeamMember::withoutGlobalScopes()->create([
                    'user_id' => $week->user_id,
                    'game_week_id' => $week->id,
                    'companion_id' => $companion->id,
                    'ability_key' => $ability['key'],
                    'ability_label' => $ability['label'],
                    'slot' => ++$slot,
                ]);
            }
        } finally {
            mt_srand();
        }
    }

    /**
     * @param  Collection<int, GameCompanion>  $candidates
     * @return array<int, GameCompanion>
     */
    private function weightedSample(Collection $candidates, Collection $appearances, int $count): array
    {
        $pool = $candidates->values()->all();
        $picked = [];
        while ($pool !== [] && count($picked) < $count) {
            $weights = array_map(fn (GameCompanion $c) => 1000 / (1 + (int) ($appearances[$c->id] ?? 0)), $pool);
            $roll = mt_rand(1, (int) array_sum($weights));
            foreach ($weights as $index => $weight) {
                $roll -= $weight;
                if ($roll <= 0) {
                    $picked[] = $pool[$index];
                    array_splice($pool, $index, 1);
                    break;
                }
            }
        }

        return $picked;
    }

    /** @return array<int, array{key: string, label: string}> */
    private function abilities(int $userId): array
    {
        return $this->categories($userId)
            ->map(fn ($name, $key) => ['key' => (string) $key, 'label' => (string) $name])
            ->values()
            ->merge(collect(config('game.scopes'))->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values())
            ->all();
    }

    /** @return Collection<string, string> key => nombre */
    private function categories(int $userId): Collection
    {
        return TaskCategory::withoutGlobalScopes()->where('user_id', $userId)
            ->orderBy('sort_order')->orderBy('name')->pluck('name', 'key');
    }

    private function createMissions(GameWeek $week): void
    {
        // Reglas de la temporada vigente al iniciar la semana (CHG-022); si no hay, las base.
        $rules = app(GameRules::class)->for($week->user_id, $week->week_start->copy()->addHours(12));
        [$min, $max] = $rules['mission_target'];
        $counts = app(MissionPendingTasks::class)->counts($week->user_id);
        $categories = $this->categories($week->user_id)->only($counts->keys()->all());
        $periods = HabitDefinition::withoutGlobalScopes()->where('user_id', $week->user_id)->get(['time_of_day'])
            ->map(fn ($habit) => $habit->time_of_day ?: 'anytime')->unique()->values();
        $types = collect($rules['mission_types'])->filter(fn ($type) => match ($type) {
            GameMission::TYPE_CATEGORY => $categories->isNotEmpty(),
            GameMission::TYPE_HABITS => $periods->isNotEmpty(),
            default => true,
        })->values();

        mt_srand($week->seed);
        try {
            $created = [];
            // Cantidad primero; foco y semilla solo resuelven empates.
            $ranked = $categories->map(fn ($name, $key) => [
                'key' => $key, 'count' => $counts[$key],
                'focus' => in_array($key, $rules['mission_focus'], true) ? 1 : 0,
                'tie' => mt_rand(),
            ])->sort(fn ($a, $b) => ($b['count'] <=> $a['count']) ?: ($b['focus'] <=> $a['focus']) ?: ($a['tie'] <=> $b['tie']))->values();
            for ($i = 0; $i < (int) config('game.missions_per_week') && $types->isNotEmpty(); $i++) {
                $type = $types[$i % $types->count()];
                $target = mt_rand($min, $max);
                $mission = match ($type) {
                    GameMission::TYPE_CATEGORY => $this->categoryMission($categories, $counts, $ranked, $target),
                    GameMission::TYPE_HABITS => $this->habitsMission($periods, $target, $created),
                    default => $this->varietyMission($categories->count() + ($periods->isNotEmpty() ? 1 : 0), $target),
                };
                if (! $mission || in_array($mission['title'], $created, true)) {
                    continue;
                }
                $created[] = $mission['title'];
                GameMission::withoutGlobalScopes()->create([
                    'user_id' => $week->user_id,
                    'game_week_id' => $week->id,
                    'type' => $type,
                ] + $mission);
            }
        } finally {
            mt_srand();
        }
    }

    /** Variedad: la meta no supera los ámbitos disponibles (categorías + hábitos). */
    private function varietyMission(int $scopes, int $target): ?array
    {
        $target = min($target, $scopes);
        if ($target < 2) {
            return null;
        }

        return ['title' => "Avanza en {$target} categorías distintas", 'params' => [], 'target' => $target];
    }

    private function categoryMission(Collection $categories, Collection $counts, Collection $ranked, int $target): ?array
    {
        $candidate = $ranked->shift();
        if (! $candidate) {
            return null;
        }
        $key = $candidate['key'];
        $name = $categories[$key];
        $target = min($target, $counts[$key]);
        $tasks = $target === 1 ? 'tarea' : 'tareas';

        return ['title' => "Completa {$target} {$tasks} de {$name}", 'params' => ['category' => $key, 'category_name' => $name], 'target' => $target];
    }

    /** @param array<int, string> $created */
    private function habitsMission(Collection $periods, int $target, array $created): ?array
    {
        $period = $periods[mt_rand(0, $periods->count() - 1)];
        $label = self::HABIT_PERIOD_LABELS[$period] ?? self::HABIT_PERIOD_LABELS['anytime'];
        return ['title' => "Completa tus hábitos de {$label} {$target} días", 'params' => ['period' => $period], 'target' => $target];
    }
}
