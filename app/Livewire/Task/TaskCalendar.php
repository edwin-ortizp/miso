<?php

namespace App\Livewire\Task;

use App\Models\Task;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Calendario de tareas')]
class TaskCalendar extends Component
{
    // Contrato del editor compartido: lo abre al cargar y lo limpia al cerrarse.
    #[Url(as: 'edit')]
    public ?string $editTask = null;

    /**
     * Eventos de FullCalendar para el rango visible [$start, $end).
     */
    public function events(string $start, string $end): array
    {
        $rangeStart = Carbon::parse($start);
        $rangeEnd = Carbon::parse($end);

        return Task::query()
            ->where(fn ($query) => $query->whereNotNull('start_date')->orWhereNotNull('end_date'))
            ->where(fn ($query) => $query->whereRaw('COALESCE(start_date, end_date) < ?', [$rangeEnd])
                ->whereRaw('COALESCE(end_date, start_date) >= ?', [$rangeStart->copy()->startOfDay()]))
            ->get()
            ->map(fn (Task $task) => $this->toEvent($task))
            ->values()
            ->all();
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
            'classNames' => array_values(array_filter([
                'md-calendar-task',
                $task->completed ? 'is-completed' : null,
                $task->priority ? 'is-'.$task->priority : null,
            ])),
        ];
    }

    public function render()
    {
        return view('livewire.task.task-calendar', [
            'unscheduledTasks' => Task::query()->whereNull('start_date')->whereNull('end_date')->where('completed', false)->orderByDesc('created_at')->get(),
        ]);
    }
}
