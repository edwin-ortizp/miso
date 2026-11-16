<?php

namespace App\Support;

use App\Models\EnergyEntry;
use App\Models\HealthMeasurement;
use App\Models\SleepLog;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * CHG-128: series diarias, resumen y dirección cautelosa de las mediciones y del sueño, y la
 * correlación sueño–energía. Solo describe lo registrado: no diagnostica ni afirma causas.
 * Usa los scopes del usuario autenticado.
 */
final class HealthTrends
{
    public const PERIODS = [30 => '30 días', 90 => '90 días'];

    /** Días con datos necesarios para hablar de una dirección. */
    public const MIN_DAYS_FOR_DIRECTION = 6;

    /** Cambio relativo mínimo entre la primera y la segunda mitad para decir «tiende a». */
    public const DIRECTION_THRESHOLD = 0.05;

    public const MIN_PAIRED_DAYS = 7;

    public const MIN_GROUP_DAYS = 2;

    public const MIN_ENERGY_DIFFERENCE = 0.5;

    private static function start(int $days, ?CarbonInterface $now): Carbon
    {
        return ($now ? Carbon::instance($now) : now())->setTimezone(config('app.timezone'))->startOfDay()->subDays($days - 1);
    }

    /**
     * Mediciones de un tipo: un punto por día con el promedio del día.
     *
     * @return array{type: string, definition: array, from: Carbon, days: int, points: Collection, summary: array}
     */
    public static function measurement(string $type, int $days, ?CarbonInterface $now = null, bool $includeSensitive = true): array
    {
        $from = self::start($days, $now);
        $rows = HealthMeasurement::where('type', $type)->where('measured_at', '>=', $from)
            ->when(! $includeSensitive, fn ($q) => $q->where('is_sensitive', false))
            ->orderBy('measured_at')->get();

        $points = $rows->groupBy(fn (HealthMeasurement $m) => $m->measured_at->toDateString())->map(function (Collection $day, string $date) use ($from) {
            return [
                'date' => Carbon::parse($date, config('app.timezone')),
                'offset' => (int) $from->diffInDays(Carbon::parse($date, config('app.timezone'))),
                'value' => round($day->avg('value'), 2),
                'value2' => $day->whereNotNull('value2')->isEmpty() ? null : round($day->avg('value2'), 2),
                'count' => $day->count(),
                'out_of_range' => $day->contains(fn (HealthMeasurement $m) => $m->isOutOfRange()),
            ];
        })->values();

        return [
            'type' => $type,
            'definition' => HealthMeasurement::TYPES[$type],
            'from' => $from,
            'days' => $days,
            'points' => $points,
            'summary' => [
                'count' => $rows->count(),
                'days' => $points->count(),
                'average' => $rows->isEmpty() ? null : round($rows->avg('value'), 1),
                'average2' => $rows->whereNotNull('value2')->isEmpty() ? null : round($rows->whereNotNull('value2')->avg('value2'), 1),
                'min' => $rows->isEmpty() ? null : (float) $rows->min('value'),
                'max' => $rows->isEmpty() ? null : (float) $rows->max('value'),
                'out_of_range' => $rows->filter(fn (HealthMeasurement $m) => $m->isOutOfRange())->count(),
                'direction' => self::direction($points->pluck('value')->all()),
                'latest' => $rows->last(),
            ],
        ];
    }

    /**
     * Sueño: un punto por día de despertar con el total de minutos dormidos ese día.
     *
     * @return array{from: Carbon, days: int, points: Collection, goal: int, summary: array}
     */
    public static function sleep(int $days, ?CarbonInterface $now = null, bool $includeSensitive = true): array
    {
        $from = self::start($days, $now);
        $logs = SleepLog::where('woke_at', '>=', $from)->when(! $includeSensitive, fn ($q) => $q->where('is_sensitive', false))
            ->orderBy('woke_at')->get();
        $goal = SleepProgress::goalMinutes(auth()->user());

        $points = $logs->groupBy(fn (SleepLog $l) => $l->woke_at->toDateString())->map(function (Collection $day, string $date) use ($from, $goal) {
            $minutes = (int) $day->sum(fn (SleepLog $l) => $l->durationMinutes());

            return [
                'date' => Carbon::parse($date, config('app.timezone')),
                'offset' => (int) $from->diffInDays(Carbon::parse($date, config('app.timezone'))),
                'value' => round($minutes / 60, 2),
                'value2' => null,
                'count' => $day->count(),
                'out_of_range' => $minutes < $goal,
            ];
        })->values();

        $summary = SleepProgress::summary($logs, $goal);

        return [
            'from' => $from,
            'days' => $days,
            'points' => $points,
            'goal' => $goal,
            'summary' => [
                'count' => $summary['nights'],
                'days' => $points->count(),
                'average_minutes' => $summary['average_minutes'],
                'meeting_goal' => $summary['meeting_goal'],
                'min_minutes' => $logs->isEmpty() ? null : (int) $logs->min(fn (SleepLog $l) => $l->durationMinutes()),
                'max_minutes' => $logs->isEmpty() ? null : (int) $logs->max(fn (SleepLog $l) => $l->durationMinutes()),
                'direction' => self::direction($points->pluck('value')->all()),
            ],
        ];
    }

    /**
     * «up», «down» o «stable» comparando el promedio de la primera y la segunda mitad; null si hay pocos días.
     *
     * @param  list<float|int>  $values
     */
    public static function direction(array $values): ?string
    {
        if (count($values) < self::MIN_DAYS_FOR_DIRECTION) {
            return null;
        }
        $half = intdiv(count($values), 2);
        $first = array_sum(array_slice($values, 0, $half)) / $half;
        $second = array_sum(array_slice($values, -$half)) / $half;
        if ($first == 0.0) {
            return 'stable';
        }
        $change = ($second - $first) / abs($first);

        return abs($change) < self::DIRECTION_THRESHOLD ? 'stable' : ($change > 0 ? 'up' : 'down');
    }

    public static function directionText(?string $direction): ?string
    {
        return match ($direction) {
            'up' => 'Tiende a subir',
            'down' => 'Tiende a bajar',
            'stable' => 'Se mantiene estable',
            default => null,
        };
    }

    /**
     * Energía promedio de los días con sueño de meta cumplida frente a los que no.
     * Devuelve null si no hay datos suficientes o la diferencia es pequeña.
     *
     * @return array{direction: string, met: float, unmet: float, days: int, text: string}|null
     */
    public static function sleepEnergy(int $days, ?CarbonInterface $now = null, bool $includeSensitive = true): ?array
    {
        $sleep = self::sleep($days, $now, $includeSensitive);
        $goalHours = $sleep['goal'] / 60;
        $energy = EnergyEntry::where('date', '>=', $sleep['from']->toDateString())->get()
            ->groupBy(fn (EnergyEntry $e) => $e->date->toDateString())->map(fn (Collection $day) => $day->avg('level'));

        $met = [];
        $unmet = [];
        foreach ($sleep['points'] as $point) {
            $key = $point['date']->toDateString();
            if (! $energy->has($key)) {
                continue;
            }
            if ($point['value'] >= $goalHours) {
                $met[] = $energy[$key];
            } else {
                $unmet[] = $energy[$key];
            }
        }

        if (count($met) + count($unmet) < self::MIN_PAIRED_DAYS || count($met) < self::MIN_GROUP_DAYS || count($unmet) < self::MIN_GROUP_DAYS) {
            return null;
        }
        $metAvg = round(array_sum($met) / count($met), 1);
        $unmetAvg = round(array_sum($unmet) / count($unmet), 1);
        if (abs($metAvg - $unmetAvg) < self::MIN_ENERGY_DIFFERENCE) {
            return null;
        }
        $higher = $metAvg > $unmetAvg;
        $fmt = fn (float $n) => str_replace('.', ',', (string) $n);

        return [
            'direction' => $higher ? 'higher' : 'lower',
            'met' => $metAvg,
            'unmet' => $unmetAvg,
            'days' => count($met) + count($unmet),
            'text' => 'Los días que dormiste al menos tu meta, tu energía tiende a ser '.($higher ? 'más alta' : 'más baja')
                .' ('.$fmt($metAvg).' frente a '.$fmt($unmetAvg).' de 5). Esto no implica una causa.',
        ];
    }
}
