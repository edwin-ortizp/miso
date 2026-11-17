<?php

namespace App\Services\Task;

use App\Models\Medication;
use App\Models\Task;
use App\Services\Meal\MealSchedule;
use App\Support\MedicationSchedule;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * CHG-142: calcula huecos libres y cruces de agenda de un usuario.
 *
 * Ocupan la agenda las tareas con hora (no completadas), las comidas planificadas y las tomas de
 * medicamentos. Las citas de salud y los planes no tienen hora y no bloquean. Todo se interpreta en la
 * zona de la aplicación. Sin Livewire: lo comparten la ventana «Buscar un hueco» y el MCP.
 */
class TaskSlotFinder
{
    public const MAX_RANGE_DAYS = 14;

    public const DEFAULT_MINUTES = 60;

    /** Duración de una toma de medicamento dentro de la agenda. */
    private const DOSE_MINUTES = 15;

    /** Cuando una tarea con hora no trae fin ni estimación se supone esta duración. */
    private const TASK_FALLBACK_MINUTES = 60;

    /** Los inicios sugeridos se alinean a esta cuadrícula (además del inicio exacto de cada hueco). */
    private const GRID_MINUTES = 30;

    public function __construct(private PlanningHours $hours, private MealSchedule $meals) {}

    /**
     * Huecos libres de `$minutes` minutos entre los días de `$from` y `$to` (ambos incluidos).
     *
     * Opciones: `part` (any|morning|afternoon), `limit`, `exclude_task_id`, `now`.
     *
     * @return list<array{start: Carbon, end: Carbon, minutes: int}>
     */
    public function find(int $userId, int $minutes, CarbonInterface $from, CarbonInterface $to, array $options = []): array
    {
        $tz = config('app.timezone');
        $minutes = max(5, min(1440, $minutes));
        [$first, $last] = $this->range($from, $to);
        $now = isset($options['now']) ? Carbon::instance($options['now'])->setTimezone($tz) : now($tz);
        $earliest = $now->copy()->second(0)->addMinutes((5 - $now->minute % 5) % 5 ?: 0);
        $part = $options['part'] ?? 'any';
        $limit = max(1, min(100, (int) ($options['limit'] ?? 30)));
        $margin = $this->hours->margin($userId);

        $busy = $this->mergeIntervals(array_map(
            fn (array $block) => ['start' => $block['start']->copy()->subMinutes($margin), 'end' => $block['end']->copy()->addMinutes($margin)],
            $this->busy($userId, $first, $last->copy()->endOfDay(), $options['exclude_task_id'] ?? null),
        ));

        $slots = [];
        for ($day = $first->copy(); $day->lte($last); $day->addDay()) {
            $window = $this->hours->window($userId, $day);
            if (! $window) {
                continue;
            }
            $windowStart = $window['start']->lt($earliest) ? $earliest->copy() : $window['start'];
            if ($windowStart->gte($window['end'])) {
                continue;
            }
            foreach ($this->freeIntervals($windowStart, $window['end'], $busy) as [$freeStart, $freeEnd]) {
                foreach ($this->candidates($freeStart, $freeEnd, $minutes) as $start) {
                    if (! $this->matchesPart($start, $part)) {
                        continue;
                    }
                    $slots[] = ['start' => $start, 'end' => $start->copy()->addMinutes($minutes), 'minutes' => $minutes];
                    if (count($slots) >= $limit) {
                        return $slots;
                    }
                }
            }
        }

        return $slots;
    }

    /**
     * Grupos de bloques que se solapan y que incluyen al menos una tarea.
     *
     * @return list<array{start: Carbon, end: Carbon, items: list<array<string, mixed>>}>
     */
    public function conflicts(int $userId, CarbonInterface $from, CarbonInterface $to): array
    {
        [$first, $last] = $this->range($from, $to);
        $blocks = $this->busy($userId, $first, $last->copy()->endOfDay());
        usort($blocks, fn ($a, $b) => $a['start'] <=> $b['start']);

        $groups = [];
        $current = null;
        foreach ($blocks as $block) {
            if ($current && $block['start']->lt($current['end'])) {
                $current['items'][] = $block;
                if ($block['end']->gt($current['end'])) {
                    $current['end'] = $block['end']->copy();
                }

                continue;
            }
            if ($current) {
                $groups[] = $current;
            }
            $current = ['start' => $block['start']->copy(), 'end' => $block['end']->copy(), 'items' => [$block]];
        }
        if ($current) {
            $groups[] = $current;
        }

        return array_values(array_filter($groups, fn ($group) => count($group['items']) > 1
            && collect($group['items'])->contains(fn ($item) => $item['source'] === 'task')));
    }

    /**
     * Bloques que ocupan la agenda en `[$from, $to]`.
     *
     * @return list<array{id: string, title: string, source: string, start: Carbon, end: Carbon, movable: bool}>
     */
    public function busy(int $userId, CarbonInterface $from, CarbonInterface $to, ?string $excludeTaskId = null): array
    {
        $tz = config('app.timezone');
        $from = Carbon::instance($from)->setTimezone($tz);
        $to = Carbon::instance($to)->setTimezone($tz);

        return [...$this->taskBlocks($userId, $from, $to, $excludeTaskId), ...$this->mealBlocks($userId, $from, $to), ...$this->medicationBlocks($userId, $from, $to)];
    }

    /** @return list<array<string, mixed>> */
    private function taskBlocks(int $userId, Carbon $from, Carbon $to, ?string $excludeTaskId): array
    {
        $tasks = Task::withoutGlobalScopes()->where('user_id', $userId)
            ->where('completed', false)
            ->whereNotNull('start_date')
            ->where('start_is_date', false)
            ->where('start_date', '<', $to)
            ->where(fn ($query) => $query->where('start_date', '>=', $from->copy()->subDay())
                ->orWhere('end_date', '>', $from))
            ->when($excludeTaskId, fn ($query) => $query->whereKeyNot($excludeTaskId))
            ->get();

        $blocks = [];
        foreach ($tasks as $task) {
            $start = Carbon::instance($task->start_date)->setTimezone(config('app.timezone'));
            $end = ($task->end_date && ! $task->end_is_date && $task->end_date->gt($task->start_date))
                ? Carbon::instance($task->end_date)->setTimezone(config('app.timezone'))
                : $start->copy()->addMinutes($task->estimated_time ?: self::TASK_FALLBACK_MINUTES);
            if ($end->lte($from) || $start->gte($to)) {
                continue;
            }
            $blocks[] = ['id' => (string) $task->id, 'title' => (string) $task->title, 'source' => 'task', 'start' => $start, 'end' => $end,
                'movable' => ! (bool) ($task->schedule_locked ?? false)];
        }

        return $blocks;
    }

    /** @return list<array<string, mixed>> */
    private function mealBlocks(int $userId, Carbon $from, Carbon $to): array
    {
        $blocks = [];
        // `events()` compara fechas con `<=`; se amplía un día para no perder las comidas del último día en SQLite.
        foreach ($this->meals->events($userId, $from, $to->copy()->addDay()) as $event) {
            if (Carbon::parse($event['start'])->gte($to) || Carbon::parse($event['end'])->lte($from)) {
                continue;
            }
            $blocks[] = ['id' => (string) $event['id'], 'title' => (string) $event['title'], 'source' => 'meal',
                'start' => Carbon::parse($event['start'])->setTimezone(config('app.timezone')),
                'end' => Carbon::parse($event['end'])->setTimezone(config('app.timezone')), 'movable' => false];
        }

        return $blocks;
    }

    /** @return list<array<string, mixed>> */
    private function medicationBlocks(int $userId, Carbon $from, Carbon $to): array
    {
        $medications = Medication::withoutGlobalScopes()->where('user_id', $userId)->where('is_active', true)->get();
        if ($medications->isEmpty()) {
            return [];
        }
        $blocks = [];
        for ($day = $from->copy()->startOfDay(); $day->lte($to); $day->addDay()) {
            foreach ($medications as $medication) {
                foreach (MedicationSchedule::slots($medication, $day) as $slot) {
                    $start = Carbon::instance($slot)->setTimezone(config('app.timezone'));
                    $end = $start->copy()->addMinutes(self::DOSE_MINUTES);
                    if ($end->gt($from) && $start->lt($to)) {
                        $blocks[] = ['id' => 'medication:'.$medication->id.':'.$start->format('YmdHi'), 'title' => 'Toma de medicamento',
                            'source' => 'medication', 'start' => $start, 'end' => $end, 'movable' => false];
                    }
                }
            }
        }

        return $blocks;
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function range(CarbonInterface $from, CarbonInterface $to): array
    {
        $tz = config('app.timezone');
        $first = Carbon::parse($from->toDateString(), $tz)->startOfDay();
        $last = Carbon::parse($to->toDateString(), $tz)->startOfDay();
        if ($last->lt($first)) {
            throw new InvalidArgumentException('El rango termina antes de empezar.');
        }
        if ($first->diffInDays($last) + 1 > self::MAX_RANGE_DAYS) {
            throw new InvalidArgumentException('El rango es demasiado grande. Pide como máximo '.self::MAX_RANGE_DAYS.' días.');
        }

        return [$first, $last];
    }

    /**
     * @param  list<array{start: Carbon, end: Carbon}>  $intervals
     * @return list<array{start: Carbon, end: Carbon}>
     */
    private function mergeIntervals(array $intervals): array
    {
        usort($intervals, fn ($a, $b) => $a['start'] <=> $b['start']);
        $merged = [];
        foreach ($intervals as $interval) {
            $lastKey = array_key_last($merged);
            if ($lastKey !== null && $interval['start']->lte($merged[$lastKey]['end'])) {
                if ($interval['end']->gt($merged[$lastKey]['end'])) {
                    $merged[$lastKey]['end'] = $interval['end'];
                }

                continue;
            }
            $merged[] = $interval;
        }

        return $merged;
    }

    /**
     * @param  list<array{start: Carbon, end: Carbon}>  $busy  Intervalos ocupados, ya fusionados y ordenados.
     * @return list<array{0: Carbon, 1: Carbon}>
     */
    private function freeIntervals(Carbon $start, Carbon $end, array $busy): array
    {
        $free = [];
        $cursor = $start->copy();
        foreach ($busy as $block) {
            if ($block['end']->lte($cursor)) {
                continue;
            }
            if ($block['start']->gte($end)) {
                break;
            }
            if ($block['start']->gt($cursor)) {
                $free[] = [$cursor->copy(), $block['start']->copy()];
            }
            if ($block['end']->gt($cursor)) {
                $cursor = $block['end']->copy();
            }
            if ($cursor->gte($end)) {
                break;
            }
        }
        if ($cursor->lt($end)) {
            $free[] = [$cursor->copy(), $end->copy()];
        }

        return $free;
    }

    /** @return list<Carbon> Inicios posibles: el del hueco y los de la cuadrícula que quepan. */
    private function candidates(Carbon $start, Carbon $end, int $minutes): array
    {
        $starts = [];
        if ($start->copy()->addMinutes($minutes)->lte($end)) {
            $starts[] = $start->copy();
        }
        $grid = $start->copy()->second(0)->minute(intdiv($start->minute, self::GRID_MINUTES) * self::GRID_MINUTES);
        while ($grid->lte($start)) {
            $grid->addMinutes(self::GRID_MINUTES);
        }
        for (; $grid->copy()->addMinutes($minutes)->lte($end); $grid->addMinutes(self::GRID_MINUTES)) {
            $starts[] = $grid->copy();
        }

        return $starts;
    }

    private function matchesPart(Carbon $start, string $part): bool
    {
        return match ($part) {
            'morning' => $start->hour < 12,
            'afternoon' => $start->hour >= 12,
            default => true,
        };
    }
}
