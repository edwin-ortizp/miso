<?php

namespace App\Observers\Game;

use App\Models\ModuleSetting;
use App\Models\PomodoroSession;
use App\Services\Game\RewardGranter;
use Illuminate\Support\Facades\DB;

/**
 * Foco (CHG-027): una ball cuando los minutos completados del día alcanzan la
 * meta diaria del módulo Pomodoro. No premia cada pomodoro (evita el exceso).
 */
class FocusSourceObserver
{
    public function saved(PomodoroSession $session): void
    {
        if (! $session->completed || ! $session->user_id || ! $session->date) {
            return;
        }

        DB::afterCommit(function () use ($session): void {
            $userId = (int) $session->user_id;
            $date = $session->date->toDateString();
            $settings = ModuleSetting::withoutGlobalScopes()->where('user_id', $userId)->where('module', 'pomodoro')->first()?->settings ?? [];
            $goal = (int) ($session->date->isWeekend()
                ? ($settings['weekend_goal_minutes'] ?? 120)
                : ($settings['weekday_goal_minutes'] ?? 300));
            if ($goal <= 0) {
                return;
            }

            $seconds = (int) PomodoroSession::withoutGlobalScopes()->where('user_id', $userId)
                ->whereDate('date', $date)->where('completed', true)->sum('duration');
            if (intdiv($seconds, 60) >= $goal) {
                app(RewardGranter::class)->forFocusDay($session, $userId, $date);
            }
        });
    }
}
