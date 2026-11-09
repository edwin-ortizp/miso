<?php

namespace App\Observers\Game;

use App\Models\DrinkLog;
use App\Models\ExerciseLog;
use App\Models\User;
use App\Services\Game\RewardGranter;
use App\Support\ExerciseProgress;
use App\Support\WaterGoal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Salud (CHG-027): una ball el día que se cumple la meta de agua y otra el día
 * que se cumple la de ejercicio. Seguir sumando no da más; borrar no quita nada.
 */
class HealthSourceObserver
{
    public function saved(Model $log): void
    {
        if (! $log->user_id || ! $log->date) {
            return;
        }

        DB::afterCommit(function () use ($log): void {
            $userId = (int) $log->user_id;
            $user = User::query()->find($userId);
            $date = $log->date->toDateString();

            if ($log instanceof DrinkLog) {
                $total = (int) DrinkLog::withoutGlobalScopes()->where('user_id', $userId)->whereDate('date', $date)->sum('hydration_value');
                if ($total >= WaterGoal::forUser($user)) {
                    app(RewardGranter::class)->forHealthDay($log, $userId, 'water', $date);
                }

                return;
            }

            if ($log instanceof ExerciseLog) {
                $goal = (int) ($user?->daily_exercise_minutes ?: ExerciseProgress::DEFAULT_DAILY_MINUTES);
                $total = (int) ExerciseLog::withoutGlobalScopes()->where('user_id', $userId)->whereDate('date', $date)->sum('duration');
                if ($goal > 0 && $total >= $goal) {
                    app(RewardGranter::class)->forHealthDay($log, $userId, 'exercise', $date);
                }
            }
        });
    }
}
