<?php

namespace App\Services\Game\Guardian;

use App\Models\Task;
use App\Models\TaskCategory;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/** Consejos de Tareas (CHG-057): próxima tarea, carga del día, vencidas viejas, racha y variedad. 3 consultas. */
class TaskAdvice
{
    use Advice;

    /** @return list<array{key: string, html: string, weight: int}> */
    public function candidates(CarbonInterface $now): array
    {
        $out = [];
        $labels = TaskCategory::options();
        $start = $now->copy()->startOfDay();
        $end = $now->copy()->endOfDay();

        $today = Task::query()->where('completed', false)
            ->where(fn ($q) => $q->whereBetween('start_date', [$start, $end])->orWhereBetween('end_date', [$start, $end]))
            ->get(['id', 'title', 'category', 'start_date', 'start_is_date', 'estimated_time']);

        $next = $today->filter(fn ($t) => ! $t->start_is_date && $t->start_date && $t->start_date->gt($now))
            ->sortBy('start_date')->first();
        if ($next) {
            $out[] = $this->candidate('tasks', 'next', ['task' => $next->title, 'time' => $next->start_date->format('H:i')], 3, ['task']);
        }

        $planned = (int) $today->sum('estimated_time');
        $left = (int) floor($now->diffInMinutes($end, true));
        if ($planned > 0 && $planned > $left) {
            $out[] = $this->candidate('tasks', 'overload', ['planned' => self::minutes($planned), 'left' => self::minutes($left)], 3);
        }

        $roots = $today->map(fn ($t) => Str::before($labels[$t->category] ?? (string) $t->category, ' / '))->unique();
        if ($today->count() >= 3 && $roots->count() === 1 && $roots->first() !== '') {
            $out[] = $this->candidate('tasks', 'single', ['category' => $roots->first()], 1, ['category']);
        }

        $stale = Task::query()->overdue()
            ->whereRaw('COALESCE(end_date, start_date) < ?', [$start->copy()->subDays(7)])
            ->selectRaw('category, COUNT(*) as total')->groupBy('category')
            ->having('total', '>=', 3)->orderByDesc('total')->first();
        if ($stale) {
            $out[] = $this->candidate('tasks', 'stale', ['count' => $stale->total, 'category' => $labels[$stale->category] ?? ($stale->category ?: 'Sin categoría')], 2, ['category']);
        }

        $done = Task::query()->where('completed', true)
            ->whereBetween('completed_at', [$start->copy()->subWeeks(4), $end])->pluck('completed_at');
        $todayCount = $done->filter(fn ($d) => $d->gte($start))->count();
        $past = $done->filter(fn ($d) => $d->lt($start) && $d->dayOfWeek === $now->dayOfWeek)->count() / 4;
        if ($todayCount >= 2 && $todayCount > $past) {
            $weekday = $now->translatedFormat('l');
            $out[] = $this->candidate('tasks', 'streak', ['count' => $todayCount, 'weekday' => str_ends_with($weekday, 's') ? $weekday : $weekday.'s'], 2);
        }

        return $out;
    }
}
