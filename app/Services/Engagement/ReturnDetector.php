<?php

namespace App\Services\Engagement;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * CHG-071: ¿el usuario vuelve hoy tras una ausencia? Compara con el último día
 * activo ANTERIOR a hoy en `user_active_days` (CHG-066). Sin historial no hay
 * regreso: un usuario nuevo nunca ve la bienvenida.
 */
class ReturnDetector
{
    /** @return array{days_away: int, last_active: string, first_missed: string}|null */
    public function detect(int $userId, ?CarbonImmutable $today = null): ?array
    {
        $today ??= CarbonImmutable::today();
        $last = DB::table('user_active_days')->where('user_id', $userId)
            ->where('date', '<', $today->toDateString())->max('date');
        if ($last === null) {
            return null;
        }

        $lastDay = CarbonImmutable::parse(substr((string) $last, 0, 10));
        $away = (int) $lastDay->diffInDays($today);
        if ($away < (int) config('engagement.return_after_days', 3)) {
            return null;
        }
        $seen = DB::table('welcome_backs')->where('user_id', $userId)
            ->where('return_date', $today->toDateString())->exists();

        return $seen ? null : [
            'days_away' => $away,
            'last_active' => $lastDay->toDateString(),
            'first_missed' => $lastDay->addDay()->toDateString(),
        ];
    }

    /** Registra que la bienvenida de hoy ya se atendió (idempotente). */
    public function close(int $userId, int $daysAway, string $outcome, ?CarbonImmutable $today = null): void
    {
        DB::table('welcome_backs')->insertOrIgnore([
            'user_id' => $userId, 'return_date' => ($today ?? CarbonImmutable::today())->toDateString(),
            'days_away' => $daysAway, 'outcome' => $outcome, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
