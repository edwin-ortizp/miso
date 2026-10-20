<?php

namespace App\Livewire\Task;

use App\Models\Task;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Gantt de tareas')]
class TaskGantt extends Component
{
    // Contrato del editor compartido: lo abre al cargar y lo limpia al cerrarse.
    #[Url(as: 'edit')]
    public ?string $editTask = null;

    /**
     * Barras de Frappe Gantt: granularidad de día, fin inclusivo.
     */
    public function tasks(): array
    {
        return Task::query()
            ->where(fn ($query) => $query->whereNotNull('start_date')->orWhereNotNull('end_date'))
            ->orderByRaw('COALESCE(start_date, end_date)')
            ->get()
            ->map(function (Task $task) {
                [$start, $end] = $this->range($task);

                return [
                    'id' => $task->id,
                    'name' => $task->title,
                    'start' => $start->toDateString(),
                    'end' => $end->toDateString(),
                    'progress' => $task->completed ? 100 : 0,
                    // Frappe aplica la clase con classList.add: debe ser un único token.
                    'custom_class' => match (true) {
                        (bool) $task->completed => 'md-gantt-task--completed',
                        $task->priority === 'urgent-important' => 'md-gantt-task--urgent',
                        default => 'md-gantt-task',
                    },
                ];
            })
            ->values()
            ->all();
    }

    /** Mueve o estira la tarea por días conservando la hora de cada extremo. */
    public function moveTask(string $id, string $start, string $end): void
    {
        $task = Task::findOrFail($id);
        $newStart = Carbon::parse($start)->startOfDay();
        $newEnd = Carbon::parse($end)->startOfDay();
        $atDay = fn (?Carbon $original, Carbon $day) => $original ? $day->copy()->setTime($original->hour, $original->minute) : $day;

        // Una tarea con un solo extremo sigue teniendo un solo extremo.
        if (! $task->start_date || ! $task->end_date) {
            $field = $task->start_date ? 'start_date' : 'end_date';
            $task->update([$field => $atDay($task->{$field}, $newStart)]);

            return;
        }

        $startAt = $atDay($task->start_date, $newStart);
        $endAt = $atDay($task->end_date, $newEnd);
        if ($endAt->lt($startAt)) {
            $endAt = $startAt->copy();
        }

        $task->update([
            'start_date' => $startAt,
            'end_date' => $endAt,
            'estimated_time' => $task->start_is_date || $task->end_is_date ? $task->estimated_time : (int) $startAt->diffInMinutes($endAt),
        ]);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function range(Task $task): array
    {
        $start = ($task->start_date ?? $task->end_date)->copy()->startOfDay();
        $end = ($task->end_date ?? $task->start_date)->copy()->startOfDay();

        return $end->lt($start) ? [$end, $start] : [$start, $end];
    }

    public function render()
    {
        return view('livewire.task.task-gantt', [
            'unscheduledTasks' => Task::query()->whereNull('start_date')->whereNull('end_date')->where('completed', false)->orderByDesc('created_at')->get(),
        ]);
    }
}
