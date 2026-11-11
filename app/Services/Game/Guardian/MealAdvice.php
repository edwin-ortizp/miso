<?php

namespace App\Services\Game\Guardian;

use App\Livewire\Home\Dashboard;
use App\Models\MealPlanEntry;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/** Consejos de Comidas (CHG-064): próxima comida de hoy, comidas sin marcar y días sin plan. 1 consulta. */
class MealAdvice
{
    use Advice;

    /** @return list<array{key: string, html: string, weight: int}> */
    public function candidates(CarbonInterface $now): array
    {
        $out = [];
        $today = $now->copy()->startOfDay();
        $weekStart = $now->copy()->startOfWeek();
        $entries = MealPlanEntry::query()->whereBetween('date', [$weekStart, $now->copy()->endOfWeek()])
            ->get(['date', 'meal_type', 'scheduled_time', 'status']);
        $label = fn ($type) => Dashboard::MEAL_TYPES[$type] ?? Str::ucfirst((string) $type);
        $clock = $now->format('H:i');
        $todayEntries = $entries->filter(fn ($e) => $e->date->isSameDay($today) && $e->status !== 'consumed');

        $next = $todayEntries->filter(fn ($e) => $e->scheduled_time && substr((string) $e->scheduled_time, 0, 5) > $clock)
            ->sortBy('scheduled_time')->first();
        if ($next) {
            $out[] = $this->candidate('meals', 'next', ['meal' => $label($next->meal_type), 'time' => substr((string) $next->scheduled_time, 0, 5)], 3, ['meal']);
        }

        $pending = $todayEntries->filter(fn ($e) => $e->scheduled_time && substr((string) $e->scheduled_time, 0, 5) <= $now->copy()->subHour()->format('H:i'))
            ->sortByDesc('scheduled_time')->first();
        if ($pending) {
            $out[] = $this->candidate('meals', 'pending', ['meal' => $label($pending->meal_type)], 2, ['meal']);
        }

        $planned = $entries->map(fn ($e) => $e->date->toDateString())->unique()->flip();
        $missing = collect(range(0, 6))->map(fn ($d) => $weekStart->copy()->addDays($d))
            ->filter(fn ($d) => $d->gte($today) && ! isset($planned[$d->toDateString()]))->count();
        if ($missing >= 2) {
            $out[] = $this->candidate('meals', 'unplanned', ['days' => $missing], 2);
        }

        return $out;
    }
}
