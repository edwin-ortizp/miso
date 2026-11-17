<?php

namespace App\Livewire\Task;

use App\Actions\Task\ScheduleTaskInSlot;
use App\Models\Task;
use App\Services\Task\PlanningHours;
use App\Services\Task\TaskSlotFinder;
use Carbon\Carbon;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * CHG-143: ventana «Buscar un hueco». Propone horarios libres para una tarea (según su duración y el horario
 * para planificar de la persona) y la agenda en el elegido, con «Deshacer».
 */
class FindSlot extends Component
{
    public const PARTS = ['any' => 'Cualquiera', 'morning' => 'Mañana', 'afternoon' => 'Tarde'];

    public const RANGES = ['this' => 'Esta semana', 'next' => 'La próxima semana'];

    public const DURATIONS = [15 => '15 min', 30 => '30 min', 60 => '1 h', 120 => '2 h'];

    public bool $open = false;

    public ?string $taskId = null;

    public string $minutes = '60';

    public string $range = 'this';

    public string $part = 'any';

    /** Inicio elegido (formato `Y-m-d H:i`, zona de la app). */
    public ?string $selected = null;

    public ?string $feedback = null;

    /** @var array<string, mixed>|null */
    public ?array $undo = null;

    public ?string $undoTaskId = null;

    #[On('task-find-slot')]
    public function openFor(string $id): void
    {
        abort_unless(auth()->check(), 403);
        $task = Task::where('user_id', auth()->id())->findOrFail($id);
        $this->feedback = null;
        $this->undo = null;
        if ($task->schedule_locked) {
            $this->feedback = 'Esta tarea tiene horario fijo. Quita el candado para moverla.';

            return;
        }
        if ($task->completed) {
            $this->feedback = 'Esta tarea ya está completa.';

            return;
        }

        $this->taskId = $task->id;
        $this->minutes = (string) $this->initialMinutes($task);
        $this->range = 'this';
        $this->part = 'any';
        $this->selected = null;
        $this->resetErrorBag();
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
        $this->selected = null;
        $this->resetErrorBag();
    }

    public function updatedMinutes(): void
    {
        $this->selected = null;
    }

    public function updatedRange(): void
    {
        $this->selected = null;
    }

    public function updatedPart(): void
    {
        $this->selected = null;
    }

    public function select(string $start): void
    {
        $this->resetErrorBag();
        $this->selected = $start;
    }

    public function schedule(TaskSlotFinder $finder): void
    {
        abort_unless(auth()->check(), 403);
        $this->resetErrorBag();
        $task = Task::where('user_id', auth()->id())->findOrFail($this->taskId);
        if ($task->schedule_locked) {
            $this->addError('selected', 'Esta tarea tiene horario fijo. Quita el candado para moverla.');

            return;
        }
        if (! $this->selected) {
            $this->addError('selected', 'Elige un horario.');

            return;
        }

        $minutes = $this->duration();
        $slot = collect($this->slots($finder, $task))->first(fn ($candidate) => $candidate['start']->format('Y-m-d H:i') === $this->selected);
        if (! $slot) {
            // Otro bloque ocupó el hueco mientras decidías: se recalcula.
            $this->selected = null;
            $this->addError('selected', 'Ese horario ya no está libre. Te muestro otros.');

            return;
        }

        $this->undo = ScheduleTaskInSlot::handle($task, $slot['start'], $minutes);
        $this->undoTaskId = $task->id;
        $label = $slot['start']->translatedFormat('l j \d\e F').' a las '.$slot['start']->format('H:i');
        $this->feedback = "Tarea agendada el {$label}.";
        $this->open = false;
        $this->selected = null;
        $this->dispatch('task-records-changed')->to(TaskList::class);
        $this->dispatch('task-calendar-refresh');
        $this->dispatch('task-editor-saved');
    }

    public function undoSchedule(): void
    {
        abort_unless(auth()->check(), 403);
        if ($this->undo && $this->undoTaskId) {
            ScheduleTaskInSlot::restore(Task::where('user_id', auth()->id())->findOrFail($this->undoTaskId), $this->undo);
            $this->feedback = 'Listo, la tarea quedó como estaba.';
            $this->dispatch('task-records-changed')->to(TaskList::class);
            $this->dispatch('task-calendar-refresh');
            $this->dispatch('task-editor-saved');
        }
        $this->undo = null;
        $this->undoTaskId = null;
    }

    public function dismissFeedback(): void
    {
        $this->feedback = null;
        $this->undo = null;
    }

    private function initialMinutes(Task $task): int
    {
        $minutes = (int) $task->estimated_time;

        return $minutes >= 5 && $minutes <= 480 ? $minutes : TaskSlotFinder::DEFAULT_MINUTES;
    }

    private function duration(): int
    {
        return max(5, min(480, (int) $this->minutes));
    }

    /** @return list<array{start: Carbon, end: Carbon, minutes: int}> */
    private function slots(TaskSlotFinder $finder, Task $task): array
    {
        $today = Carbon::today(config('app.timezone'));
        [$from, $to] = $this->range === 'next'
            ? [$today->copy()->next(Carbon::MONDAY), $today->copy()->next(Carbon::MONDAY)->addDays(6)]
            : [$today, $today->isSunday() ? $today->copy() : $today->copy()->next(Carbon::SUNDAY)];

        return $finder->find((int) auth()->id(), $this->duration(), $from, $to, [
            'part' => $this->part, 'exclude_task_id' => $task->id, 'limit' => 60,
        ]);
    }

    public function render(TaskSlotFinder $finder, PlanningHours $hours)
    {
        $task = $this->open && $this->taskId ? Task::where('user_id', auth()->id())->find($this->taskId) : null;
        $groups = [];
        $dayBlocks = [];
        $slots = [];
        $selectedSlot = null;
        $userId = (int) auth()->id();
        if ($task) {
            $slots = $this->slots($finder, $task);
            foreach ($slots as $slot) {
                $groups[$slot['start']->toDateString()][] = $slot;
            }
            $selectedSlot = collect($slots)->first(fn ($slot) => $slot['start']->format('Y-m-d H:i') === $this->selected);
            if ($selectedSlot) {
                $day = $selectedSlot['start']->copy()->startOfDay();
                $dayBlocks = collect($finder->busy($userId, $day, $day->copy()->endOfDay(), $task->id))
                    ->map(fn ($block) => $block + ['kind' => 'busy'])->push([
                        'title' => 'Aquí agendarías «'.$task->title.'»', 'source' => 'proposal', 'start' => $selectedSlot['start'], 'end' => $selectedSlot['end'], 'kind' => 'slot',
                    ])->sortBy(fn ($block) => $block['start']->timestamp)->values()->all();
            }
        }

        // Resumen de la jornada para la nota «Según tu horario…».
        $window = $hours->window($userId, Carbon::today(config('app.timezone')));

        return view('livewire.task.find-slot', [
            'task' => $task,
            'groups' => $groups,
            'dayBlocks' => $dayBlocks,
            'window' => $window,
            'selectedLabel' => $selectedSlot
                ? ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'][$selectedSlot['start']->dayOfWeek].' '.$selectedSlot['start']->day.' · '.$selectedSlot['start']->format('H:i') : null,
        ]);
    }
}
