<?php

namespace App\Services\Game\Guardian;

use App\Models\HabitCompletion;
use App\Models\HabitDefinition;
use App\Services\HabitGamificationService;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Consejos de Hábitos (CHG-058): el mensaje del coach (con "Ir al hábito sugerido"),
 * pendientes del momento, racha en riesgo y hábito más olvidado del mes. 2 consultas.
 */
class HabitAdvice
{
    use Advice;

    public function __construct(private readonly HabitGamificationService $gamification) {}

    /** @return list<array{key: string, html: string, weight: int, action?: array{label: string, target: string}}> */
    public function candidates(CarbonInterface $now): array
    {
        $out = [];
        $today = $now->copy()->startOfDay();
        $habits = HabitDefinition::orderBy('base_time')->get();
        if ($habits->isEmpty()) {
            return [];
        }

        $completed = HabitCompletion::query()->where('completed', true)
            ->whereBetween('date', [$today->copy()->subDays(45), $now->copy()->endOfDay()])
            ->get(['habit_id', 'date'])->groupBy('habit_id')
            ->map(fn ($g) => $g->map(fn ($c) => $c->date->toDateString())->flip());
        $doneToday = fn ($id) => isset($completed[$id][$today->toDateString()]);

        $coach = $this->gamification->coachCard(Carbon::parse($today), $habits,
            $habits->mapWithKeys(fn ($h) => [$h->id => $doneToday($h->id)])->all());
        $coachCandidate = ['key' => 'coach', 'html' => e($coach['message']), 'weight' => 3];
        if ($coach['suggestedHabitId']) {
            $coachCandidate['action'] = ['label' => 'Ir al hábito sugerido', 'target' => 'habit-item-'.$coach['suggestedHabitId']];
        }
        $out[] = $coachCandidate;

        $hour = $now->hour;
        $moment = match (true) { $hour >= 6 && $hour < 12 => 'morning', $hour >= 12 && $hour < 18 => 'afternoon', default => 'night' };
        $pending = $habits->where('time_of_day', $moment)->reject(fn ($h) => $doneToday($h->id));
        if ($pending->isNotEmpty()) {
            $shortest = $pending->sortBy(fn ($h) => (int) ($h->goal_duration ?: PHP_INT_MAX))->first();
            $out[] = $this->candidate('habits', 'moment', [
                'count' => $pending->count(), 'moment' => config("habit_gamification.periods.{$moment}.label"), 'habit' => $shortest->name,
            ], 2, ['habit']);
        }

        foreach ($habits->reject(fn ($h) => $doneToday($h->id)) as $habit) {
            $streak = 0;
            while (isset($completed[$habit->id][$today->copy()->subDays($streak + 1)->toDateString()])) {
                $streak++;
            }
            if ($streak >= 3) {
                $out[] = $this->candidate('habits', 'risk', ['habit' => $habit->name, 'days' => $streak], 3, ['habit']);
                break;
            }
        }

        $monthStart = $now->copy()->startOfMonth();
        $elapsed = (int) $monthStart->diffInDays($today) + 1;
        $missed = $habits->map(fn ($h) => [$h, $elapsed - collect($completed[$h->id] ?? [])->keys()
            ->filter(fn ($d) => $d >= $monthStart->toDateString())->count()])
            ->sortByDesc(fn ($p) => $p[1])->first();
        if ($missed && $missed[1] >= 5) {
            $out[] = $this->candidate('habits', 'forgotten', ['habit' => $missed[0]->name, 'days' => $missed[1]], 1, ['habit']);
        }

        return $out;
    }
}
