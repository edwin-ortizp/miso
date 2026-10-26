<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Closure;
use Illuminate\Support\Collection;

/**
 * Calendario mensual y racha de una meta diaria (hidratación, minutos activos…).
 * Recibe los totales por fecha (`Y-m-d` => total) ya calculados por cada módulo.
 */
class GoalCalendar
{
    /**
     * Límites de la cuadrícula del mes (de lunes a domingo completos).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function gridBounds(Carbon $selected, ?Carbon $monthStart = null): array
    {
        $month = ($monthStart ?? $selected)->copy()->startOfMonth();

        return [$month->copy()->startOfWeek(Carbon::MONDAY), $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY)];
    }

    /**
     * Normaliza la meta: un entero fijo o un resolver por fecha (p. ej. meta distinta en fin de semana).
     *
     * @param  int|Closure(Carbon): int  $goal
     * @return Closure(Carbon): int
     */
    private static function goalResolver(int|Closure $goal): Closure
    {
        return $goal instanceof Closure ? $goal : fn () => $goal;
    }

    /**
     * @param  int|Closure(Carbon): int  $goal
     */
    public static function month(Collection $totals, Carbon $selected, int|Closure $goal, ?Carbon $monthStart = null): array
    {
        $month = ($monthStart ?? $selected)->copy()->startOfMonth();
        [$gridStart, $gridEnd] = self::gridBounds($selected, $monthStart);
        $goalFor = self::goalResolver($goal);

        $days = collect(CarbonPeriod::create($gridStart, $gridEnd))->map(function (Carbon $date) use ($month, $selected, $totals, $goalFor) {
            $total = (int) ($totals[$date->toDateString()] ?? 0);
            $goal = (int) $goalFor($date);

            return [
                'date' => $date->copy(),
                'goal' => $goal,
                'total' => $total,
                'percentage' => $goal > 0 ? min((int) round(($total / $goal) * 100), 100) : 0,
                'raw_percentage' => $goal > 0 ? (int) round(($total / $goal) * 100) : 0,
                'has_data' => $total > 0,
                'future' => $date->gt(today()),
                'completed' => $goal > 0 && $total >= $goal,
                'in_month' => $date->month === $month->month,
                'selected' => $date->isSameDay($selected),
                'today' => $date->isToday(),
            ];
        });
        $monthDays = $days->where('in_month', true);

        return [
            'label' => $month->translatedFormat('F Y'),
            'weeks' => $days->chunk(7),
            'tracked_days' => $monthDays->where('total', '>', 0)->count(),
            'completed_days' => $monthDays->where('completed', true)->count(),
            'average' => (int) round($monthDays->avg('total') ?? 0),
        ];
    }

    /**
     * Días consecutivos cumpliendo al menos el 100 % de la meta.
     * Cuenta hacia atrás desde ayer; hoy suma solo si ya alcanzó la meta y, si aún no, no rompe la racha.
     */
    /**
     * @param  int|Closure(Carbon): int  $goal
     */
    public static function streak(Collection $totals, int|Closure $goal, ?Carbon $today = null): int
    {
        $goalFor = self::goalResolver($goal);
        $met = function (Carbon $day) use ($totals, $goalFor): bool {
            $goal = (int) $goalFor($day);

            return $goal > 0 && ($totals[$day->toDateString()] ?? 0) >= $goal;
        };

        $today = ($today ?? today())->copy()->startOfDay();
        $streak = $met($today) ? 1 : 0;

        for ($day = $today->copy()->subDay(); $met($day); $day->subDay()) {
            $streak++;
        }

        return $streak;
    }
}
