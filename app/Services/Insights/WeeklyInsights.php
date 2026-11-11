<?php

namespace App\Services\Insights;

use App\Models\DrinkLog;
use App\Models\EnergyEntry;
use App\Models\ExerciseLog;
use App\Models\HabitCompletion;
use App\Models\HabitDefinition;
use App\Models\MoodEntry;
use App\Models\Task;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\WeeklyInsight;
use App\Services\Notifications\NotificationSource;
use App\Support\WaterGoal;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Ramsey\Uuid\Uuid;

/**
 * CHG-071: hasta 3 hallazgos sobre las últimas 8 semanas, sin IA. Solo se
 * muestra un hallazgo con datos suficientes en cada grupo y una diferencia
 * mínima; el lenguaje es prudente ("tiende a"), nunca causal.
 */
class WeeklyInsights
{
    public const TYPE = 'weekly_insights';

    public const SOURCE = 'insights';

    private const WEEKS = 8;

    private const MIN_DAYS = 6;

    private const MIN_DIFF = 0.5;

    private const MIN_TASK_RATIO = 1.25;

    private const WEEKDAYS = [1 => 'lunes', 2 => 'martes', 3 => 'miércoles', 4 => 'jueves', 5 => 'viernes', 6 => 'sábados', 7 => 'domingos'];

    public static function weekStart(CarbonInterface $date): CarbonImmutable
    {
        return CarbonImmutable::instance($date)->setTimezone(NotificationSource::TIMEZONE)->startOfWeek(CarbonInterface::MONDAY)->startOfDay();
    }

    public static function sourceId(): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, 'miso:insights')->toString();
    }

    /** Calcula (o devuelve) los hallazgos de la semana de `$date`. Idempotente por semana. */
    public function forWeek(User $user, CarbonInterface $date): WeeklyInsight
    {
        $week = self::weekStart($date);
        $existing = WeeklyInsight::where('user_id', $user->id)->whereDate('week_start', $week->toDateString())->first();
        if ($existing) {
            return $existing;
        }

        return WeeklyInsight::firstOrCreate(
            ['user_id' => $user->id, 'week_start' => $week->toDateString()],
            ['findings' => $this->compute($user, $week->addDays(6))],
        );
    }

    /** Aviso del domingo en la campana y por push (si el usuario lo activó). Una vez por semana. */
    public function notify(User $user, CarbonInterface $now): ?UserNotification
    {
        $insight = $this->forWeek($user, $now);
        $first = $insight->findings[0]['text'] ?? null;
        if ($first === null) {
            return null;
        }
        $notice = UserNotification::withoutGlobalScopes()->firstOrCreate([
            'user_id' => $user->id, 'source_type' => self::SOURCE, 'source_id' => self::sourceId(),
            'type' => self::TYPE, 'occurrence_key' => $insight->week_start->toDateString(),
        ], ['title' => 'Tu resumen de la semana', 'body' => mb_strimwidth($first, 0, 120, '…'), 'scheduled_for' => now('UTC')]);

        return $notice->wasRecentlyCreated ? $notice : null;
    }

    /** @return list<array{key: string, value: string, text: string}> */
    public function compute(User $user, CarbonImmutable $end): array
    {
        $from = $end->subWeeks(self::WEEKS)->addDay()->toDateString();
        $to = $end->toDateString();
        $owned = fn (string $model) => $model::withoutGlobalScopes()->where('user_id', $user->id);

        $energy = $this->dailyAvg($owned(EnergyEntry::class), 'level', $from, $to);
        $mood = $this->dailyAvg($owned(MoodEntry::class)->whereNotNull('value'), 'value', $from, $to);
        $exerciseDays = $owned(ExerciseLog::class)->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)
            ->selectRaw('DATE(date) as d')->groupBy('d')->pluck('d')->map(fn ($d) => substr((string) $d, 0, 10))->flip();
        $goal = max(1, WaterGoal::forUser($user));
        $waterDays = $owned(DrinkLog::class)->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)
            ->selectRaw('DATE(date) as d, SUM(hydration_value) as total')->groupBy('d')->get()
            ->filter(fn ($r) => (float) $r->total >= $goal)->pluck('d')->map(fn ($d) => substr((string) $d, 0, 10))->flip();
        $habitCount = $owned(HabitDefinition::class)->count();
        $habitDays = $habitCount === 0 ? collect() : $owned(HabitCompletion::class)->where('completed', true)->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)
            ->selectRaw('DATE(date) as d, COUNT(*) as n')->groupBy('d')->get()
            ->filter(fn ($r) => (int) $r->n >= $habitCount)->pluck('d')->map(fn ($d) => substr((string) $d, 0, 10))->flip();

        $findings = array_filter([
            $this->compare('exercise_energy', $energy, $exerciseDays, 'Los días que haces ejercicio, tu energía tiende a ser %s puntos más %s.'),
            $this->compare('exercise_mood', $mood, $exerciseDays, 'Los días que haces ejercicio, tu ánimo tiende a ser %s puntos más %s.'),
            $this->compare('water_energy', $energy, $waterDays, 'Los días que cumples tu meta de agua, tu energía tiende a ser %s puntos más %s.'),
            $this->compare('habits_mood', $mood, $habitDays, 'Los días que completas todos tus hábitos, tu ánimo tiende a ser %s puntos más %s.'),
            $this->bestTaskDay($owned(Task::class), $from, $to),
        ]);
        usort($findings, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_map(fn ($f) => array_intersect_key($f, array_flip(['key', 'value', 'text'])), array_slice($findings, 0, 3));
    }

    /** @return Collection<string, float> promedio por día */
    private function dailyAvg($query, string $column, string $from, string $to): Collection
    {
        return $query->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)
            ->selectRaw("DATE(date) as d, AVG({$column}) as v")->groupBy('d')->get()
            ->mapWithKeys(fn ($r) => [substr((string) $r->d, 0, 10) => (float) $r->v]);
    }

    private function compare(string $key, Collection $values, Collection $flagDays, string $template): ?array
    {
        [$with, $without] = $values->partition(fn ($v, $day) => $flagDays->has($day));
        if ($with->count() < self::MIN_DAYS || $without->count() < self::MIN_DAYS) {
            return null;
        }
        $diff = $with->avg() - $without->avg();
        if (abs($diff) < self::MIN_DIFF) {
            return null;
        }
        $value = number_format(abs($diff), 1, ',', '');

        return [
            'key' => $key, 'score' => abs($diff),
            'value' => ($diff > 0 ? '+' : '−').$value,
            'text' => sprintf($template, $value, $diff > 0 ? 'alta' : 'baja'),
        ];
    }

    private function bestTaskDay($tasks, string $from, string $to): ?array
    {
        $byDay = $tasks->where('completed', true)->whereBetween('completed_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->pluck('completed_at')
            ->countBy(fn ($at) => CarbonImmutable::parse($at)->setTimezone(NotificationSource::TIMEZONE)->isoWeekday());
        if ($byDay->sum() < self::MIN_DAYS * 2 || $byDay->count() < 3) {
            return null;
        }
        $best = $byDay->sortDesc()->keys()->first();
        $others = $byDay->except($best);
        $avgOthers = $others->sum() / 6;
        if ($avgOthers > 0 && $byDay[$best] / $avgOthers < self::MIN_TASK_RATIO) {
            return null;
        }
        $day = self::WEEKDAYS[$best];

        return [
            'key' => 'tasks_weekday', 'score' => 0.6,
            'value' => mb_convert_case(mb_substr($day, 0, 3), MB_CASE_TITLE),
            'text' => 'Los '.$day.' es cuando más tareas completas.',
        ];
    }
}
