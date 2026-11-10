<?php

namespace App\Services\Game\Guardian;

use App\Models\DrinkLog;
use App\Support\WaterGoal;
use Carbon\CarbonInterface;

/** Consejos de Hidratación (CHG-058): lo que falta, ritmo vs otros días, horas sin agua y racha. 1 consulta. */
class WaterAdvice
{
    use Advice;

    /** @return list<array{key: string, html: string, weight: int}> */
    public function candidates(CarbonInterface $now): array
    {
        $out = [];
        $goal = WaterGoal::forUser(auth()->user());
        $today = $now->copy()->startOfDay();
        $clock = $now->format('H:i');

        $logs = DrinkLog::query()->whereBetween('date', [$today->copy()->subDays(30), $now->copy()->endOfDay()])
            ->get(['date', 'time', 'hydration_value']);
        $byDay = $logs->groupBy(fn ($l) => $l->date->toDateString());
        $todayLogs = $byDay[$today->toDateString()] ?? collect();
        $consumed = (int) $todayLogs->sum('hydration_value');

        if ($goal > 0 && $consumed < $goal) {
            $out[] = $this->candidate('water', 'left', ['left' => self::liters($goal - $consumed)], 2);
        }

        $usual = collect(range(1, 7))->map(fn ($d) => (int) ($byDay[$today->copy()->subDays($d)->toDateString()] ?? collect())
            ->filter(fn ($l) => substr((string) $l->time, 0, 5) <= $clock)->sum('hydration_value'))->avg();
        if ($usual > 0 && $consumed < $usual && $consumed < $goal) {
            $out[] = $this->candidate('water', 'pace', ['now' => self::liters($consumed), 'usual' => self::liters((int) round($usual))], 3);
        }

        if ($now->hour >= 7 && $now->hour < 22) {
            $last = $todayLogs->map(fn ($l) => substr((string) $l->time, 0, 5))->max();
            $since = $last ? $today->copy()->setTimeFromTimeString($last) : $today->copy()->setTime(7, 0);
            $hours = (int) floor($since->diffInMinutes($now, true) / 60);
            if ($hours >= 3) {
                $out[] = $this->candidate('water', 'gap', ['hours' => $hours], 3);
            }
        }

        $streak = 0;
        for ($d = $consumed >= $goal ? 0 : 1; $d <= 30; $d++) {
            if ($goal <= 0 || (int) ($byDay[$today->copy()->subDays($d)->toDateString()] ?? collect())->sum('hydration_value') < $goal) {
                break;
            }
            $streak++;
        }
        if ($streak >= 2) {
            $out[] = $this->candidate('water', 'streak', ['days' => $streak], 2);
        }

        return $out;
    }

    private static function liters(int $ml): string
    {
        return number_format($ml / 1000, 1, ',', '.').' L';
    }
}
