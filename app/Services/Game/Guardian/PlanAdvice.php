<?php

namespace App\Services\Game\Guardian;

use App\Models\Plan;
use App\Models\PlanVisit;
use Carbon\CarbonInterface;

/** Consejos de Planes (CHG-064): plan agendado pronto, planes por estrenar y último plan hace tiempo. 2 consultas. */
class PlanAdvice
{
    use Advice;

    /** @return list<array{key: string, html: string, weight: int}> */
    public function candidates(CarbonInterface $now): array
    {
        $out = [];
        $today = $now->copy()->startOfDay();
        $plans = Plan::query()->whereIn('status', ['pending', 'scheduled'])->withCount('visits')
            ->get(['id', 'title', 'status', 'scheduled_on']);

        $soon = $plans->filter(fn ($p) => $p->scheduled_on && $p->scheduled_on->gte($today) && $p->scheduled_on->lte($today->copy()->addDays(7)))
            ->sortBy('scheduled_on')->first();
        if ($soon) {
            $days = (int) $today->diffInDays($soon->scheduled_on->copy()->startOfDay());
            $when = match (true) { $days === 0 => 'para hoy', $days === 1 => 'para mañana', default => 'para el '.$soon->scheduled_on->translatedFormat('l') };
            $out[] = $this->candidate('plans', 'soon', ['plan' => $soon->title, 'when' => $when], 3, ['plan']);
        }

        $fresh = $plans->filter(fn ($p) => $p->visits_count === 0 && $p->status === 'pending');
        if ($fresh->count() >= 3) {
            $out[] = $this->candidate('plans', 'pending', ['count' => $fresh->count(), 'plan' => $fresh->random()->title], 2, ['plan']);
        }

        $last = PlanVisit::query()->max('visited_on');
        if ($last) {
            $days = (int) \Illuminate\Support\Carbon::parse($last)->startOfDay()->diffInDays($today);
            if ($days >= 30) {
                $out[] = $this->candidate('plans', 'idle', ['days' => $days], 2);
            }
        }

        return $out;
    }
}
