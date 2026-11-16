<?php

namespace App\Support;

use App\Models\SleepLog;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/** CHG-125: meta de horas, avance de una noche y resumen de los últimos días. */
final class SleepProgress
{
    public const DEFAULT_HOURS = 8.0;

    public const MIN_HOURS = 4.0;

    public const MAX_HOURS = 14.0;

    public static function goalMinutes(?User $user): int
    {
        $hours = (float) ($user?->daily_sleep_hours ?: self::DEFAULT_HOURS);

        return (int) round(max(self::MIN_HOURS, min(self::MAX_HOURS, $hours)) * 60);
    }

    /** Texto neutro de la diferencia con la meta. */
    public static function goalMessage(int $minutes, int $goalMinutes): string
    {
        $difference = $minutes - $goalMinutes;

        return match (true) {
            $difference === 0 => 'Alcanzaste tu meta',
            $difference > 0 => 'Superaste tu meta por '.SleepLog::formatMinutes($difference),
            default => 'Faltaron '.SleepLog::formatMinutes(-$difference).' para tu meta',
        };
    }

    /**
     * Registros cuya hora de despertar cae en los últimos $days días (incluido hoy).
     *
     * @return Collection<int, SleepLog>
     */
    public static function recent(int $days, ?CarbonInterface $now = null): Collection
    {
        $today = ($now ?? now())->copy()->startOfDay();

        return SleepLog::where('woke_at', '>=', $today->copy()->subDays($days - 1))
            ->where('woke_at', '<', $today->copy()->addDay())->orderByDesc('woke_at')->get();
    }

    /**
     * @param  Collection<int, SleepLog>  $logs
     * @return array{nights: int, average_minutes: ?int, meeting_goal: int, average_quality: ?float}
     */
    public static function summary(Collection $logs, int $goalMinutes): array
    {
        $qualities = $logs->pluck('quality')->filter();

        return [
            'nights' => $logs->count(),
            'average_minutes' => $logs->isEmpty() ? null : (int) round($logs->avg(fn (SleepLog $l) => $l->durationMinutes())),
            'meeting_goal' => $logs->filter(fn (SleepLog $l) => $l->durationMinutes() >= $goalMinutes)->count(),
            'average_quality' => $qualities->isEmpty() ? null : round($qualities->avg(), 1),
        ];
    }
}
