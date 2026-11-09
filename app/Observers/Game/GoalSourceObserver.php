<?php

namespace App\Observers\Game;

use App\Models\Goal;
use App\Services\Game\RewardGranter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Metas (CHG-027): progreso registrado (texto o numérico) → una ball por meta y
 * día; meta pasada a `completed` → ball especial, una sola vez por meta.
 */
class GoalSourceObserver
{
    /** GoalEntry y GoalNumericEntry. */
    public function created(Model $entry): void
    {
        DB::afterCommit(function () use ($entry): void {
            $goal = Goal::withoutGlobalScopes()->find($entry->goal_id);
            if (! $goal || ! $entry->user_id) {
                return;
            }
            $date = ($entry->date ?? now())->toDateString();
            app(RewardGranter::class)->forGoalProgress($entry, (int) $entry->user_id, $goal->id, (string) $goal->title, $date);
        });
    }

    /** Goal: al quedar cumplida. */
    public function saved(Model $goal): void
    {
        if (! $goal instanceof Goal || $goal->status !== 'completed' || ! ($goal->wasRecentlyCreated || $goal->wasChanged('status'))) {
            return;
        }

        DB::afterCommit(fn () => app(RewardGranter::class)->forGoalCompleted($goal, (int) $goal->user_id, (string) $goal->title));
    }
}
