<?php

namespace App\Services\Game;

use App\Models\DrinkLog;
use App\Models\EnergyEntry;
use App\Models\Game\GameDailyChallenge;
use App\Models\HabitCompletion;
use App\Models\HabitDefinition;
use App\Models\MoodEntry;
use App\Models\Task;
use App\Models\TaskCategory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Throwable;

/**
 * Reto del día (CHG-077): un reto pequeño por usuario y fecha, elegido sin IA entre plantillas
 * que tienen datos hoy. Se prioriza el área más descuidada de la semana y, dentro de ella, una
 * semilla estable (usuario + fecha) decide. El progreso se deriva de los datos del día; al
 * cumplirse se paga una ball extra (`grantBonusBall`) una sola vez. Nunca resta.
 */
class DailyChallenge
{
    public const PERIOD_LABELS = ['morning' => 'la mañana', 'afternoon' => 'la tarde', 'night' => 'la noche', 'anytime' => 'cualquier momento'];

    /** Hora en que termina cada momento: después no se propone. */
    private const PERIOD_ENDS = ['morning' => 12, 'afternoon' => 18, 'night' => 24, 'anytime' => 24];

    public function __construct(
        private readonly GameSettings $settings,
        private readonly RewardGranter $granter,
    ) {}

    /**
     * El reto de hoy con su avance; null si el juego o el reto están apagados o no hay plantilla.
     *
     * @return array{key: string, text: string, done: int, target: int, completed: bool, url: ?string, checkin: bool}|null
     */
    public function forToday(int $userId, ?CarbonImmutable $now = null): ?array
    {
        $settings = $this->settings->for($userId);
        if (! $settings['enabled'] || ! $settings['daily_challenge']) {
            return null;
        }
        $now ??= CarbonImmutable::now(config('app.timezone'));

        try {
            $challenge = $this->challengeFor($userId, $now);
            if (! $challenge) {
                return null;
            }
            $done = $this->progress($userId, $challenge, $now);
            if ($done >= $challenge->target && ! $challenge->completed_at) {
                $challenge->update(['completed_at' => now()]);
            }
            if ($challenge->completed_at && ! $challenge->rewarded_at) {
                $this->granter->grantBonusBall($userId, 'daily-challenge:'.$now->toDateString(), 'Reto del día: '.$this->text($challenge), [
                    'icon' => 'bi-bullseye',
                    'title' => '¡Reto del día cumplido!',
                    'detail' => 'Ganaste una ball.',
                    'challenge' => $challenge->key,
                ]);
                $challenge->update(['rewarded_at' => now()]);
            }

            return [
                'key' => $challenge->key,
                'text' => $this->text($challenge),
                'done' => min($done, $challenge->target),
                'target' => $challenge->target,
                'completed' => (bool) $challenge->completed_at,
                'url' => $this->url($challenge),
                'checkin' => $challenge->key === 'checkin',
            ];
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    private function challengeFor(int $userId, CarbonImmutable $now): ?GameDailyChallenge
    {
        $date = $now->toDateString();
        $existing = GameDailyChallenge::withoutGlobalScopes()->where('user_id', $userId)->whereDate('date', $date)->first();
        if ($existing) {
            return $existing;
        }

        $picked = $this->pick($userId, $now);
        if (! $picked) {
            return null;
        }

        try {
            return GameDailyChallenge::withoutGlobalScopes()->create(['user_id' => $userId, 'date' => $date] + $picked);
        } catch (QueryException) {
            // Otra pestaña lo eligió primero: se usa ese.
            return GameDailyChallenge::withoutGlobalScopes()->where('user_id', $userId)->whereDate('date', $date)->first();
        }
    }

    /** @return array{key: string, module: string, params: array<string, mixed>, target: int}|null */
    private function pick(int $userId, CarbonImmutable $now): ?array
    {
        $enabled = (array) config('game.daily_challenge.templates', []);
        $candidates = array_values(array_filter(array_map(
            fn (string $key) => $this->candidate($key, $userId, $now),
            $enabled,
        )));
        if ($candidates === []) {
            return null;
        }

        // Prioridad por área descuidada: solo compiten las plantillas del área con menor avance semanal.
        $ratios = [];
        foreach ($candidates as $candidate) {
            $ratios[$candidate['module']] ??= $this->weeklyRatio($candidate['module'], $userId, $now);
        }
        $lowest = min($ratios);
        $pool = array_values(array_filter($candidates, fn (array $candidate) => $ratios[$candidate['module']] <= $lowest + 0.0001));

        return $pool[crc32($userId.'|'.$now->toDateString()) % count($pool)];
    }

    /** @return array{key: string, module: string, params: array<string, mixed>, target: int}|null */
    private function candidate(string $key, int $userId, CarbonImmutable $now): ?array
    {
        $goal = (array) config("game.daily_challenge.targets.{$key}", [2, 2]);
        $n = $goal[0] + (crc32($key.'|'.$userId.'|'.$now->toDateString()) % max(1, $goal[1] - $goal[0] + 1));

        return match ($key) {
            'tasks_done' => $this->tasks($userId)->where('completed', false)->count() >= $n
                ? ['key' => $key, 'module' => 'tasks', 'params' => ['n' => $n], 'target' => $n] : null,
            'category_tasks' => $this->categoryCandidate($userId, $n),
            'habit_period' => $this->habitCandidate($userId, $now),
            'water_logs' => ['key' => $key, 'module' => 'water', 'params' => ['n' => $n], 'target' => $n],
            'checkin' => $this->checkinDone($userId, $now) ? null : ['key' => $key, 'module' => 'checkin', 'params' => [], 'target' => 1],
            default => null,
        };
    }

    private function categoryCandidate(int $userId, int $n): ?array
    {
        $category = $this->tasks($userId)->where('completed', false)->whereNotNull('category')->where('category', '!=', '')
            ->selectRaw('category, count(*) as pending')->groupBy('category')->orderByDesc('pending')->orderBy('category')
            ->first();
        if (! $category || (int) $category->pending < $n) {
            return null;
        }
        $name = TaskCategory::withoutGlobalScopes()->where('user_id', $userId)->where('key', $category->category)->value('name') ?? $category->category;

        return ['key' => 'category_tasks', 'module' => 'tasks', 'params' => ['n' => $n, 'category' => $category->category, 'name' => $name], 'target' => $n];
    }

    private function habitCandidate(int $userId, CarbonImmutable $now): ?array
    {
        $habits = HabitDefinition::withoutGlobalScopes()->where('user_id', $userId)->get(['id', 'time_of_day']);
        $completed = HabitCompletion::withoutGlobalScopes()->where('user_id', $userId)->whereDate('date', $now->toDateString())
            ->where('completed', true)->pluck('habit_id')->all();
        foreach (array_keys(self::PERIOD_LABELS) as $period) {
            if ($now->hour >= self::PERIOD_ENDS[$period]) {
                continue;
            }
            $ids = $habits->filter(fn ($habit) => ($habit->time_of_day ?: 'anytime') === $period)->pluck('id')->all();
            if ($ids !== [] && array_diff($ids, $completed) !== []) {
                return ['key' => 'habit_period', 'module' => 'habits', 'params' => ['period' => $period, 'habit_ids' => $ids], 'target' => count($ids)];
            }
        }

        return null;
    }

    private function progress(int $userId, GameDailyChallenge $challenge, CarbonImmutable $now): int
    {
        $date = $now->toDateString();
        $params = (array) $challenge->params;

        return match ($challenge->key) {
            'tasks_done' => $this->countedCompletions($userId, $now)->count(),
            'category_tasks' => $this->countedCompletions($userId, $now)->where('category', $params['category'] ?? null)->count(),
            'habit_period' => HabitCompletion::withoutGlobalScopes()->where('user_id', $userId)->whereDate('date', $date)
                ->where('completed', true)->whereIn('habit_id', (array) ($params['habit_ids'] ?? []))->count(),
            'water_logs' => DrinkLog::withoutGlobalScopes()->where('user_id', $userId)->whereDate('date', $date)->count(),
            'checkin' => $this->checkinDone($userId, $now) ? 1 : 0,
            default => 0,
        };
    }

    /**
     * Tareas cerradas hoy que cuentan para el reto. Antifarmeo: una tarea creada hoy sin fecha
     * ni recurrencia no cuenta (sí da su recompensa normal).
     */
    private function countedCompletions(int $userId, CarbonImmutable $now): Builder
    {
        $start = $now->startOfDay();

        return $this->tasks($userId)->where('completed', true)
            ->whereBetween('completed_at', [$start, $now->endOfDay()])
            ->where(fn (Builder $query) => $query->where('created_at', '<', $start)
                ->orWhereNotNull('start_date')->orWhereNotNull('end_date')
                ->orWhere('is_recurrent', true)->orWhereNotNull('recurrence_series_id'));
    }

    private function tasks(int $userId): Builder
    {
        return Task::withoutGlobalScopes()->where('user_id', $userId)->where('is_recurrence_history', false);
    }

    private function checkinDone(int $userId, CarbonImmutable $now): bool
    {
        $date = $now->toDateString();

        return MoodEntry::withoutGlobalScopes()->where('user_id', $userId)->whereDate('date', $date)->exists()
            && EnergyEntry::withoutGlobalScopes()->where('user_id', $userId)->whereDate('date', $date)->exists();
    }

    /** Avance de la semana (0 = nada, 1 = al día) del área; el reto empuja la más baja. */
    private function weeklyRatio(string $module, int $userId, CarbonImmutable $now): float
    {
        $from = $now->startOfWeek()->startOfDay();
        $days = max(1, (int) $from->diffInDays($now->startOfDay()) + 1);
        $expected = (array) config('game.daily_challenge.weekly_per_day', []);
        $done = match ($module) {
            'tasks' => $this->tasks($userId)->where('completed', true)->where('completed_at', '>=', $from)->count(),
            'habits' => HabitCompletion::withoutGlobalScopes()->where('user_id', $userId)->where('completed', true)
                ->whereDate('date', '>=', $from->toDateString())->count(),
            'water' => DrinkLog::withoutGlobalScopes()->where('user_id', $userId)->whereDate('date', '>=', $from->toDateString())->count(),
            'checkin' => MoodEntry::withoutGlobalScopes()->where('user_id', $userId)->whereDate('date', '>=', $from->toDateString())
                ->distinct()->count('date'),
            default => 0,
        };
        $perDay = $module === 'habits'
            ? max(1, HabitDefinition::withoutGlobalScopes()->where('user_id', $userId)->count())
            : max(1, (int) ($expected[$module] ?? 1));

        return min(1.0, $done / ($perDay * $days));
    }

    public function text(GameDailyChallenge $challenge): string
    {
        $params = (array) $challenge->params;

        return match ($challenge->key) {
            'tasks_done' => 'Cierra '.$challenge->target.' tareas',
            'category_tasks' => 'Cierra '.$challenge->target.' de '.($params['name'] ?? 'una categoría'),
            'habit_period' => 'Completa los hábitos de '.(self::PERIOD_LABELS[$params['period'] ?? 'anytime'] ?? 'hoy'),
            'water_logs' => 'Registra agua '.$challenge->target.' veces',
            'checkin' => 'Haz tu check-in de hoy',
            default => 'Reto de hoy',
        };
    }

    private function url(GameDailyChallenge $challenge): ?string
    {
        return match ($challenge->module) {
            'tasks' => route('tasks'),
            'habits' => route('habits'),
            'water' => route('water.daily'),
            'checkin' => route('home', ['checkin' => 1]),
            default => null,
        };
    }
}
