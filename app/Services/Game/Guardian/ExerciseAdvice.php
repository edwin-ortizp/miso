<?php

namespace App\Services\Game\Guardian;

use App\Models\ExerciseLog;
use App\Models\ExerciseType;
use App\Support\ExerciseProgress;
use Carbon\CarbonInterface;

/** Consejos de Ejercicio (CHG-058): minutos que faltan, días sin moverte, tipo olvidado y semana vs anterior. 2 consultas. */
class ExerciseAdvice
{
    use Advice;

    /** @return list<array{key: string, html: string, weight: int}> */
    public function candidates(CarbonInterface $now): array
    {
        $out = [];
        $goal = (int) (auth()->user()->daily_exercise_minutes ?: ExerciseProgress::DEFAULT_DAILY_MINUTES);
        $today = $now->copy()->startOfDay();

        $logs = ExerciseLog::query()->whereBetween('date', [$today->copy()->subDays(74), $now->copy()->endOfDay()])
            ->get(['date', 'duration', 'exercise_type_id']);
        $done = (int) $logs->filter(fn ($l) => $l->date->isSameDay($today))->sum('duration');

        if ($goal > 0 && $done < $goal) {
            $out[] = $this->candidate('exercise', 'left', ['minutes' => $goal - $done], 2);
        }

        $last = $logs->max(fn ($l) => $l->date);
        $idle = $last ? (int) $last->copy()->startOfDay()->diffInDays($today) : null;
        if ($idle !== null && $idle >= 2) {
            $out[] = $this->candidate('exercise', 'idle', ['days' => $idle], 3);
        }

        $recentCut = $today->copy()->subDays(14);
        $forgotten = $logs->filter(fn ($l) => $l->exercise_type_id)->groupBy('exercise_type_id')
            ->filter(fn ($g) => $g->every(fn ($l) => $l->date->lt($recentCut)) && $g->count() >= 3)
            ->sortByDesc(fn ($g) => $g->count())->keys()->first();
        if ($forgotten && ($type = ExerciseType::find($forgotten))) {
            $days = (int) $logs->where('exercise_type_id', $forgotten)->max(fn ($l) => $l->date)->copy()->startOfDay()->diffInDays($today);
            $out[] = $this->candidate('exercise', 'forgotten', ['days' => $days, 'type' => $type->name], 2, ['type']);
        }

        $weekStart = $now->copy()->startOfWeek();
        $thisWeek = (int) $logs->filter(fn ($l) => $l->date->gte($weekStart))->sum('duration');
        $lastWeek = (int) $logs->filter(fn ($l) => $l->date->gte($weekStart->copy()->subWeek()) && $l->date->lt($weekStart))->sum('duration');
        if ($lastWeek > 0 && $thisWeek > $lastWeek) {
            $out[] = $this->candidate('exercise', 'week', ['now' => $thisWeek, 'last' => $lastWeek], 2);
        }

        return $out;
    }
}
