<?php

namespace App\Livewire\Task;

use App\Livewire\Concerns\HandlesRecurringTaskCompletion;
use App\Models\Plan;
use App\Models\Relationship;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Services\TaskGamificationService;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Calendario de tareas')]
class TaskCalendar extends Component
{
    use HandlesRecurringTaskCompletion {
        confirmRecurringCompletion as protected completeRecurring;
    }

    private const PRIORITIES = [
        'urgent-important' => 'Urgente e importante',
        'not-urgent-important' => 'Importante, no urgente',
        'urgent-not-important' => 'Urgente, no importante',
        'not-urgent-not-important' => 'Ni urgente ni importante',
    ];

    // Contrato del editor compartido: lo abre al cargar y lo limpia al cerrarse.
    #[Url(as: 'edit')]
    public ?string $editTask = null;

    // Aviso tras programar la siguiente repetición: la tarea se mueve y puede quedar fuera de la vista.
    public ?string $notice = null;

    public ?string $noticeDate = null;

    /**
     * Eventos de FullCalendar para el rango visible [$start, $end).
     */
    public function events(string $start, string $end): array
    {
        abort_unless(auth()->check(), 403);
        $rangeStart = Carbon::parse($start);
        $rangeEnd = Carbon::parse($end);

        $events = Task::query()->where('user_id', auth()->id())->with('status')
            ->where(fn ($query) => $query->whereNotNull('start_date')->orWhereNotNull('end_date'))
            ->where(fn ($query) => $query->whereRaw('COALESCE(start_date, end_date) < ?', [$rangeEnd])
                ->whereRaw('COALESCE(end_date, start_date) >= ?', [$rangeStart->copy()->startOfDay()]))
            ->get()
            ->map(fn (Task $task) => $this->toEvent($task))
            ->values()
            ->all();

        $plans = Plan::where('user_id', auth()->id())->whereNotNull('scheduled_on')
            ->where('scheduled_on', '<', $rangeEnd->toDateString())
            ->whereRaw('COALESCE(ends_on, scheduled_on) >= ?', [$rangeStart->toDateString()])
            ->get();

        foreach ($plans as $plan) {
            $events[] = [
                'id' => 'plan:'.$plan->id,
                'title' => $plan->title,
                'start' => $plan->scheduled_on->toDateString(),
                'end' => ($plan->ends_on ?? $plan->scheduled_on)->copy()->addDay()->toDateString(),
                'allDay' => true,
                'editable' => false,
                'classNames' => ['md-calendar-plan'],
                'extendedProps' => ['type' => 'plans', 'detailUrl' => route('plans.show', $plan)],
            ];
        }

        $people = Relationship::where('user_id', auth()->id())->active()->withBirthday()->get();
        foreach ($people as $person) {
            $birthday = $person->birthday();
            if (! $birthday) {
                continue;
            }
            for ($year = $rangeStart->year; $year <= $rangeEnd->year; $year++) {
                $date = $birthday->occurrenceIn($year);
                if ($date->lt($rangeStart) || $date->gte($rangeEnd) || ($birthday->year !== null && $year < $birthday->year)) {
                    continue;
                }
                $age = $birthday->year !== null ? $year - $birthday->year : null;
                $events[] = [
                    'id' => 'birthday:'.$person->id.':'.$year,
                    'title' => $person->full_name.($age !== null ? ' · '.$age.' años' : ''),
                    'start' => $date->toDateString(),
                    'end' => $date->copy()->addDay()->toDateString(),
                    'allDay' => true,
                    'editable' => false,
                    'classNames' => ['md-calendar-birthday'],
                    'extendedProps' => ['type' => 'birthdays', 'detailUrl' => route('relationships.show', $person)],
                ];
            }
        }

        return array_merge($events, app(\App\Services\Meal\MealSchedule::class)->events((int) auth()->id(), $rangeStart, $rangeEnd));
    }

    public function moveTask(string $id, string $start, ?string $end, bool $allDay): void
    {
        $task = Task::findOrFail($id);
        $newStart = Carbon::parse($start);
        $newEnd = $end ? Carbon::parse($end) : null;

        if ($allDay) {
            // FullCalendar usa fin exclusivo en eventos de día completo.
            $newStart->startOfDay();
            $newEnd = $newEnd?->subDay()->startOfDay() ?? $newStart->copy();

            // Conserva qué extremos tenía la tarea: una tarea con solo vencimiento sigue sin inicio.
            $task->update($task->start_date ? [
                'start_date' => $newStart,
                'start_is_date' => true,
                'end_date' => $task->end_date ? $newEnd : null,
                'end_is_date' => true,
            ] : [
                'end_date' => $newStart,
                'end_is_date' => true,
            ]);

            return;
        }

        $newEnd ??= $newStart->copy()->addMinutes($task->estimated_time ?: 60);
        $task->update([
            'start_date' => $newStart,
            'start_is_date' => false,
            'end_date' => $newEnd,
            'end_is_date' => false,
            'estimated_time' => (int) $newStart->diffInMinutes($newEnd),
        ]);
    }

    public function toggleComplete(string $id, TaskGamificationService $gamification): void
    {
        $task = Task::findOrFail($id);
        if ($this->prepareRecurringCompletion($task)) {
            return;
        }

        $result = $gamification->toggle($task);
        if ($result['completed']) {
            $this->dispatch('task-completed', ...$result);
        }
        $this->dispatch('task-calendar-refresh');
    }

    public function changeStatus(string $id, string $statusId, TaskGamificationService $gamification): array
    {
        abort_unless(auth()->check(), 403);
        $task = Task::where('user_id', auth()->id())->findOrFail($id);
        $status = TaskStatus::where('user_id', auth()->id())->findOrFail($statusId);

        if ($task->status_id === $status->id) {
            return $this->statusData($task);
        }

        if ($status->type === TaskStatus::DONE) {
            $this->toggleComplete($task->id, $gamification);

            return $this->statusData($task->refresh());
        }

        if ($task->completed) {
            $gamification->reopen($task);
        }
        $task->update(['status_id' => $status->id]);
        $this->dispatch('task-calendar-refresh');

        return $this->statusData($task->refresh());
    }

    private function statusData(Task $task): array
    {
        return [
            'status' => ['id' => $task->status->id, 'name' => $task->status->name, 'icon' => $task->status->icon],
            'completed' => (bool) $task->completed,
        ];
    }

    public function confirmRecurringCompletion(TaskGamificationService $gamification): ?array
    {
        $taskId = $this->recurringTaskId;
        $result = $this->completeRecurring($gamification);
        $this->dispatch('task-calendar-refresh');

        $next = $result ? Task::find($taskId) : null;
        $date = $next?->start_date ?? $next?->end_date;
        if ($date) {
            $this->noticeDate = $date->toDateString();
            $this->notice = 'Completada. Próxima repetición: '.$date->locale('es')->translatedFormat('l j \d\e F').'.';
        }

        return $result;
    }

    private function timeLabel(Carbon $start, Carbon $end, bool $allDay): string
    {
        $day = fn (Carbon $date) => $date->locale('es')->translatedFormat('D j M');

        if ($allDay) {
            return $start->isSameDay($end) ? $day($start).' · Todo el día' : $day($start).' – '.$day($end);
        }

        return $start->isSameDay($end)
            ? $day($start).' · '.$start->format('H:i').($end->gt($start) ? '–'.$end->format('H:i') : '')
            : $day($start).' '.$start->format('H:i').' – '.$day($end).' '.$end->format('H:i');
    }

    private function toEvent(Task $task): array
    {
        $start = $task->start_date ?? $task->end_date;
        $end = $task->end_date ?? $task->start_date;
        $allDay = ($task->start_date ? $task->start_is_date : true) && ($task->end_date ? $task->end_is_date : true);

        if ($end->lt($start)) {
            [$start, $end] = [$end, $start];
        }

        return [
            'id' => $task->id,
            'title' => $task->title,
            'start' => $allDay ? $start->toDateString() : $start->toIso8601String(),
            'end' => $allDay ? $end->copy()->addDay()->toDateString() : ($end->gt($start) ? $end->toIso8601String() : null),
            'allDay' => $allDay,
            'extendedProps' => [
                'type' => 'tasks',
                'status' => $task->status ? ['id' => $task->status->id, 'name' => $task->status->name, 'icon' => $task->status->icon] : null,
                'completed' => (bool) $task->completed,
                'recurrent' => (bool) $task->is_recurrent,
                'category' => $task->category,
                'priority' => $task->priority ? (self::PRIORITIES[$task->priority] ?? null) : null,
                'timeLabel' => $this->timeLabel($start, $end, $allDay),
            ],
            'classNames' => array_values(array_filter([
                'md-calendar-task',
                $task->completed ? 'is-completed' : null,
                $task->is_recurrent ? 'is-recurrent' : null,
                $task->priority ? 'is-'.$task->priority : null,
            ])),
        ];
    }

    public function render()
    {
        return view('livewire.task.task-calendar', [
            'statuses' => TaskStatus::where('user_id', auth()->id())->ordered()->get(),
            'unscheduledTasks' => Task::query()->whereNull('start_date')->whereNull('end_date')->where('completed', false)->orderByDesc('created_at')->get(),
        ]);
    }
}
