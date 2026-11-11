<?php

namespace App\Services\Game\Guardian;

use App\Models\Goal;
use App\Models\GoalEntry;
use App\Support\GoalProgress;
use Carbon\CarbonInterface;

/** Consejos de Objetivos (CHG-063): meta que vence pronto, meta sin avances e hito reciente. 2 consultas. */
class GoalAdvice
{
    use Advice;

    /** @return list<array{key: string, html: string, weight: int}> */
    public function candidates(CarbonInterface $now): array
    {
        $out = [];
        $today = $now->copy()->startOfDay();
        $goals = Goal::query()->where('status', 'active')->get(['id', 'title', 'start_date', 'due_date', 'numeric_goal', 'created_at']);
        if ($goals->isEmpty()) {
            return [];
        }
        $entries = GoalEntry::query()->whereIn('goal_id', $goals->pluck('id'))
            ->get(['goal_id', 'date', 'is_milestone'])->groupBy('goal_id');

        $due = $goals->filter(fn ($g) => $g->due_date && $g->due_date->gte($today) && $g->due_date->lte($today->copy()->addDays(14)))
            ->sortBy('due_date')->first();
        if ($due) {
            $days = (int) $today->diffInDays($due->due_date->copy()->startOfDay());
            $progress = GoalProgress::calculate($due->numeric_goal, $due->start_date, $due->due_date, $today);
            $out[] = $progress
                ? $this->candidate('goals', 'due', ['goal' => $due->title, 'days' => $days, 'percent' => (int) round($progress['actualPercent'])], 3, ['goal'])
                : $this->candidate('goals', 'due_plain', ['goal' => $due->title, 'days' => $days], 3, ['goal']);
        }

        $idle = $goals->map(function ($g) use ($entries, $today) {
            $last = ($entries[$g->id] ?? collect())->max(fn ($e) => $e->date) ?? $g->created_at;

            return [$g, (int) $last->copy()->startOfDay()->diffInDays($today)];
        })->filter(fn ($p) => $p[1] >= 7)->sortByDesc(fn ($p) => $p[1])->first();
        if ($idle) {
            $out[] = $this->candidate('goals', 'idle', ['goal' => $idle[0]->title, 'days' => $idle[1]], 2, ['goal']);
        }

        $milestone = $goals->first(fn ($g) => ($entries[$g->id] ?? collect())
            ->contains(fn ($e) => $e->is_milestone && $e->date->gte($today->copy()->subDays(7))));
        if ($milestone) {
            $out[] = $this->candidate('goals', 'milestone', ['goal' => $milestone->title], 2, ['goal']);
        }

        return $out;
    }
}
