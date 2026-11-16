<?php

namespace App\Services\Social;

use App\Models\Friendship;
use App\Models\User;
use App\Services\Game\GameSettings;
use App\Services\Game\Streak;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Racha de dos amigos (CHG-123): un día cuenta cuando AMBOS cumplieron su racha personal.
 * Solo lee: no usa Streak::summary() (escribe filas ajenas y depende de los ajustes del amigo)
 * y nunca resta, igual que la racha personal (CHG-076).
 */
class SharedStreak
{
    private const WINDOW_DAYS = 365;

    public function __construct(
        private Streak $streak,
        private GameSettings $settings,
        private SocialSettings $social,
    ) {
    }

    /**
     * @return array{days: int, best: int, met_today: bool, grace_left: int, grace_total: int, last7: array<int, array{date: string, status: string}>, broken: bool}|null
     *         null si no hay amistad aceptada o si alguno de los dos no es visible.
     */
    public function for(User $me, int $friendId, ?CarbonImmutable $today = null): ?array
    {
        $accepted = Friendship::query()->between($me->id, $friendId)->where('status', Friendship::ACCEPTED)->exists();
        if (! $accepted || ! $this->social->visible($me->id) || ! $this->social->visible($friendId)) {
            return null;
        }

        $today ??= CarbonImmutable::now(config('app.timezone'))->startOfDay();
        $graceTotal = max(0, (int) config('game.streak.grace_days_per_month', 2));
        $from = $today->subDays(self::WINDOW_DAYS);
        $mine = $this->metDays($me->id, $from, $today);
        $theirs = $this->metDays($friendId, $from, $today);

        $todayKey = $today->toDateString();
        $metToday = ($mine['today'] ?? false) && ($theirs['today'] ?? false);
        unset($mine['today'], $theirs['today']);

        $dates = array_keys($mine + $theirs);
        sort($dates);
        $run = 0;
        $best = 0;
        $graces = [];
        $status = [];
        // Solo hay racha desde el primer día en que alguno registró algo; antes no hay nada que proteger.
        for ($day = isset($dates[0]) ? CarbonImmutable::parse($dates[0]) : $today; $day->lt($today); $day = $day->addDay()) {
            $date = $day->toDateString();
            $month = $day->format('Y-m');
            if (($mine[$date] ?? false) && ($theirs[$date] ?? false)) {
                $run++;
                $status[$date] = 'met';
            } elseif ($run > 0 && ($graces[$month] ?? 0) < $graceTotal) {
                $graces[$month] = ($graces[$month] ?? 0) + 1;
                $status[$date] = 'grace';
            } else {
                $run = 0;
                $status[$date] = 'none';
            }
            $best = max($best, $run);
        }
        $days = $run + ($metToday ? 1 : 0);
        $best = max($best, $days);

        $last7 = [];
        for ($day = $today->subDays(6); $day->lte($today); $day = $day->addDay()) {
            $date = $day->toDateString();
            $last7[] = ['date' => $date, 'status' => $date === $todayKey ? ($metToday ? 'met' : 'today') : ($status[$date] ?? 'none')];
        }

        return [
            'days' => $days,
            'best' => $best,
            'met_today' => $metToday,
            'grace_left' => max(0, $graceTotal - ($graces[$today->format('Y-m')] ?? 0)),
            'grace_total' => $graceTotal,
            'last7' => $last7,
            'broken' => $days === 0 && $best > 0,
        ];
    }

    /** Mayor hito de pareja alcanzado con esos días (o null). Los avisos los crea CHG-125. */
    public function milestoneReached(int $days): ?int
    {
        $reached = array_filter((array) config('game.streak.shared_milestones', [7, 30, 100]), fn ($n) => $days >= $n);

        return $reached ? max($reached) : null;
    }

    /**
     * Días cumplidos de una cuenta, sin escribir: «today» lleva el estado en vivo de hoy y cada fecha
     * anterior el valor fijado (o, si aún no se fijó, el que resulta con su umbral actual).
     *
     * @return array<string, bool>
     */
    private function metDays(int $userId, CarbonImmutable $from, CarbonImmutable $today): array
    {
        $threshold = (int) $this->settings->for($userId)['streak_threshold'];
        $minAreas = max(1, (int) config('game.streak.min_areas', 2));
        $rows = DB::table('user_active_days')->where('user_id', $userId)
            ->whereBetween('date', [$from->toDateString(), $today->subDay()->toDateString()])
            ->orderBy('date')->get(['date', 'records_count', 'streak_met']);

        $met = [];
        $pending = [];
        foreach ($rows as $row) {
            $date = substr((string) $row->date, 0, 10);
            if ($row->streak_met !== null) {
                $met[$date] = (bool) $row->streak_met;
            } elseif ((int) $row->records_count >= $threshold) {
                $pending[] = $date;
            } else {
                $met[$date] = false;
            }
        }
        if ($pending !== []) {
            $stats = $this->streak->dayStats($userId, CarbonImmutable::parse($pending[0]), CarbonImmutable::parse(end($pending)));
            foreach ($pending as $date) {
                $day = $stats[$date] ?? ['count' => 0, 'areas' => 0];
                $met[$date] = $day['count'] >= $threshold && $day['areas'] >= $minAreas;
            }
        }
        $now = $this->streak->dayStats($userId, $today, $today)[$today->toDateString()] ?? ['count' => 0, 'areas' => 0];
        $met['today'] = $now['count'] >= $threshold && $now['areas'] >= $minAreas;

        return $met;
    }
}
