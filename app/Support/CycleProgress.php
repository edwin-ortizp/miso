<?php

namespace App\Support;

use App\Models\CycleDayLog;
use App\Models\MenstrualCycle;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * CHG-126: día del ciclo, promedios y estimación cautelosa del próximo periodo, más el calendario del mes.
 * Lógica pura sobre los ciclos ya cargados. La estimación es informativa: no es un método anticonceptivo
 * ni un diagnóstico, y solo existe con tres o más inicios registrados.
 */
final class CycleProgress
{
    public const MIN_STARTS = 3;

    /** Ciclos fuera de este rango (días entre inicios) no entran al promedio: suelen ser un registro faltante. */
    public const MIN_CYCLE_DAYS = 15;

    public const MAX_CYCLE_DAYS = 60;

    public const WINDOW_DAYS = 2;

    /**
     * @param  Collection<int, MenstrualCycle>  $cycles
     * @return array{starts: int, intervals: int, average_cycle: ?int, average_period: ?int, predicted_start: ?Carbon, window_from: ?Carbon, window_to: ?Carbon, last_start: ?Carbon, open: bool}
     */
    public static function summary(Collection $cycles): array
    {
        $sorted = $cycles->sortBy(fn (MenstrualCycle $c) => $c->started_on->timestamp)->values();
        $starts = $sorted->map(fn (MenstrualCycle $c) => $c->started_on->copy()->startOfDay());

        $intervals = [];
        for ($i = 1; $i < $starts->count(); $i++) {
            $days = (int) $starts[$i - 1]->diffInDays($starts[$i]);
            if ($days >= self::MIN_CYCLE_DAYS && $days <= self::MAX_CYCLE_DAYS) {
                $intervals[] = $days;
            }
        }
        $periods = $sorted->map(fn (MenstrualCycle $c) => $c->periodDays())->filter()->values();

        $averageCycle = $intervals === [] ? null : (int) round(array_sum($intervals) / count($intervals));
        $last = $starts->last();
        $predicted = $starts->count() >= self::MIN_STARTS && $averageCycle !== null && $last ? $last->copy()->addDays($averageCycle) : null;

        return [
            'starts' => $starts->count(),
            'intervals' => count($intervals),
            'average_cycle' => $averageCycle,
            'average_period' => $periods->isEmpty() ? null : (int) round($periods->avg()),
            'predicted_start' => $predicted,
            'window_from' => $predicted?->copy()->subDays(self::WINDOW_DAYS),
            'window_to' => $predicted?->copy()->addDays(self::WINDOW_DAYS),
            'last_start' => $last,
            'open' => (bool) $sorted->last()?->isOpen(),
        ];
    }

    /** Día del ciclo de hoy (1 = día de inicio del último periodo) o null si aún no hay periodos. */
    public static function cycleDay(Collection $cycles, CarbonInterface $today): ?int
    {
        $last = $cycles->sortBy(fn (MenstrualCycle $c) => $c->started_on->timestamp)->last();
        if (! $last || $last->started_on->startOfDay()->gt($today->copy()->startOfDay())) {
            return null;
        }

        return (int) $last->started_on->copy()->startOfDay()->diffInDays($today->copy()->startOfDay()) + 1;
    }

    /**
     * Semanas del mes (lunes a domingo) con las marcas de cada día.
     *
     * @param  Collection<int, MenstrualCycle>  $cycles
     * @param  Collection<int, CycleDayLog>  $logs
     * @return list<list<array{date: Carbon, in_month: bool, period: bool, logged: bool, estimated: bool, today: bool}>>
     */
    public static function calendar(CarbonInterface $month, Collection $cycles, Collection $logs, array $summary, CarbonInterface $today): array
    {
        $first = Carbon::parse($month->format('Y-m-01'), config('app.timezone'))->startOfDay();
        $cursor = $first->copy()->startOfWeek(Carbon::MONDAY);
        $end = $first->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY)->startOfDay();
        $todayDate = $today->copy()->startOfDay();
        $logged = $logs->map(fn (CycleDayLog $l) => $l->date->toDateString())->flip();

        $weeks = [];
        while ($cursor->lte($end)) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $key = $cursor->toDateString();
                $week[] = [
                    'date' => $cursor->copy(),
                    'in_month' => $cursor->month === $first->month,
                    'period' => $cycles->contains(function (MenstrualCycle $c) use ($key, $todayDate) {
                        $until = $c->ended_on ? $c->ended_on->toDateString() : $todayDate->toDateString();

                        return $c->started_on->toDateString() <= $key && $key <= $until;
                    }),
                    'logged' => $logged->has($key),
                    'estimated' => $summary['window_from'] !== null && $key >= $summary['window_from']->toDateString() && $key <= $summary['window_to']->toDateString(),
                    'today' => $key === $todayDate->toDateString(),
                ];
                $cursor->addDay();
            }
            $weeks[] = $week;
        }

        return $weeks;
    }
}
