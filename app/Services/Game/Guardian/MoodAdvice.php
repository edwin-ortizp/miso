<?php

namespace App\Services\Game\Guardian;

use App\Models\EnergyEntry;
use App\Models\MoodEntry;
use Carbon\CarbonInterface;

/** Consejos de Ánimo y energía (CHG-063): registro de hoy, tendencia de 7 días y mejor franja de energía. 2 consultas. */
class MoodAdvice
{
    use Advice;

    /** @return list<array{key: string, html: string, weight: int}> */
    public function candidates(CarbonInterface $now): array
    {
        $out = [];
        $today = $now->copy()->startOfDay();

        $moods = MoodEntry::query()->whereBetween('date', [$today->copy()->subDays(13), $now->copy()->endOfDay()])->get(['date', 'value']);
        if (! $moods->contains(fn ($m) => $m->date->isSameDay($today))) {
            $out[] = $this->candidate('mood', 'missing', [], 3);
        }

        $cut = $today->copy()->subDays(6);
        $recent = $moods->filter(fn ($m) => $m->value !== null && $m->date->gte($cut))->avg('value');
        $before = $moods->filter(fn ($m) => $m->value !== null && $m->date->lt($cut))->avg('value');
        if ($recent !== null && $before !== null && abs($recent - $before) >= 0.5) {
            $out[] = $this->candidate('mood', $recent > $before ? 'up' : 'down', [], 2);
        }

        $slot = fn (int $h) => match (true) { $h < 12 => 'mañana', $h < 18 => 'tarde', default => 'noche' };
        $energy = EnergyEntry::query()->whereBetween('date', [$today->copy()->subDays(13), $now->copy()->endOfDay()])->get(['time', 'level'])
            ->groupBy(fn ($e) => $slot((int) substr((string) $e->time, 0, 2)))
            ->filter(fn ($g) => $g->count() >= 2)->map(fn ($g) => $g->avg('level'))->sortDesc();
        if ($energy->count() >= 2) {
            $out[] = $this->candidate('mood', 'energy', ['slot' => $energy->keys()->first()], 1);
        }

        return $out;
    }
}
