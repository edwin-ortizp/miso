<?php

namespace App\Services\Game\Guardian;

use App\Models\NegativeHabitDefinition;
use App\Models\NegativeHabitLog;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Consejos de Hábitos a evitar (CHG-063): días limpios, cercanía al récord y
 * apoyo tras una recaída reciente, siempre sin juzgar. 2 consultas.
 */
class NegativeHabitAdvice
{
    use Advice;

    /** @return list<array{key: string, html: string, weight: int}> */
    public function candidates(CarbonInterface $now): array
    {
        $out = [];
        $habits = NegativeHabitDefinition::query()->get(['id', 'name', 'created_at']);
        if ($habits->isEmpty()) {
            return [];
        }
        $logs = NegativeHabitLog::query()->orderBy('timestamp')->get(['habit_id', 'timestamp'])->groupBy('habit_id');
        $tz = config('app.timezone');
        $today = $now->copy()->startOfDay();

        $stats = $habits->map(function ($habit) use ($logs, $today, $tz) {
            $times = ($logs[$habit->id] ?? collect())->map(fn ($l) => Carbon::createFromTimestamp((int) $l->timestamp, $tz))->values();
            $last = $times->last();
            $clean = (int) ($last ?? $habit->created_at)->copy()->startOfDay()->diffInDays($today);
            $best = $clean;
            for ($i = 1; $i < $times->count(); $i++) {
                $best = max($best, (int) $times[$i - 1]->copy()->startOfDay()->diffInDays($times[$i]->copy()->startOfDay()));
            }

            return ['habit' => $habit, 'last' => $last, 'clean' => $clean, 'best' => $best];
        });

        $relapse = $stats->filter(fn ($s) => $s['last'] && $s['last']->gte($now->copy()->subHours(48)))->sortByDesc('last')->first();
        if ($relapse) {
            $out[] = $this->candidate('negative', 'support', ['habit' => $relapse['habit']->name], 3, ['habit']);
        }

        $clean = $stats->filter(fn ($s) => $s['clean'] >= 2)->sortByDesc('clean')->first();
        if ($clean) {
            $out[] = $this->candidate('negative', 'clean', ['days' => $clean['clean'], 'habit' => $clean['habit']->name], 2, ['habit']);
        }

        $record = $stats->filter(fn ($s) => $s['last'] && $s['best'] > $s['clean'] && $s['clean'] >= 0.8 * $s['best'])->first();
        if ($record) {
            $out[] = $this->candidate('negative', 'record', ['left' => $record['best'] - $record['clean'], 'habit' => $record['habit']->name], 2, ['habit']);
        }

        return $out;
    }
}
