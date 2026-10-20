<?php

namespace App\Livewire\Task;

use App\Livewire\Task\Concerns\FiltersTasks;
use App\Livewire\Task\Concerns\LoadsTaskCategories;
use App\Livewire\Concerns\HandlesRecurringTaskCompletion;
use App\Livewire\Concerns\InteractsWithTaskSchedule;
use App\Models\Task;
use App\Services\TaskGamificationService;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Planificación de tareas')]
class TaskPlanning extends Component
{
    use FiltersTasks, LoadsTaskCategories;
    use HandlesRecurringTaskCompletion, InteractsWithTaskSchedule;

    public bool $showForm = false;
    public string $descriptionMode = 'write';
    public ?string $editingId = null;
    public string $title = '';
    public string $description = '';
    public string $category = '';
    public string $priority = '';
    public string $size = '';
    public ?string $startDate = null;
    public ?string $endDate = null;
    public ?int $estimatedTime = null;
    public bool $isPrivate = false;


    public array $priorities = [
        'urgent-important' => '🔴 Urgente e Importante',
        'not-urgent-important' => '🟡 No Urgente pero Importante',
        'urgent-not-important' => '🟠 Urgente No Importante',
        'not-urgent-not-important' => '⚪ No Urgente No Importante',
    ];

    public array $sizes = ['XS' => 'XS (< 30 min)', 'S' => 'S (30 min - 1h)', 'M' => 'M (1-2h)', 'L' => 'L (2-4h)', 'XL' => 'XL (> 4h)'];

    public function toggleComplete(string $id, TaskGamificationService $gamification): void
    {
        $task = Task::find($id);

        if ($task) {
            if ($this->prepareRecurringCompletion($task)) {
                return;
            }

            $result = $gamification->toggle($task);

            if ($result['completed']) {
                $this->dispatch('task-completed', ...$result);
            }
        }
    }

    public function moveToDay(string $id, int $daysFromToday): void
    {
        $task = Task::find($id);

        if (! $task) {
            return;
        }

        $scheduledAt = $task->start_date ?? $task->end_date;
        $targetDay = today()->addDays($daysFromToday);
        $target = $scheduledAt
            ? $scheduledAt->copy()->setDate($targetDay->year, $targetDay->month, $targetDay->day)
            : $targetDay->copy()->startOfDay();

        if ($task->start_date && $task->end_date) {
            $scheduledDate = $task->start_date->copy()->startOfDay();
            $shift = $scheduledDate->diffInDays($target, false);
            $task->update([
                'start_date' => $task->start_date->copy()->addDays($shift)->format('Y-m-d H:i:s'),
                'end_date' => $task->end_date->copy()->addDays($shift)->format('Y-m-d H:i:s'),
            ]);
            return;
        }

        if ($task->start_date) {
            $task->update(['start_date' => $target->format('Y-m-d H:i:s')]);
            return;
        }

        $task->update(['end_date' => $target->format('Y-m-d H:i:s')]);
    }

    public function clearDates(string $id): void
    {
        Task::whereKey($id)->update(['start_date' => null, 'end_date' => null]);
    }

    public function openForm(string $id): void
    {
        $task = Task::find($id);

        if (! $task) {
            return;
        }

        $this->editingId = $task->id;
        $this->title = $task->title;
        $this->description = $task->description ?? '';
        $this->category = $task->category ?? '';
        $this->priority = $task->priority ?? '';
        $this->size = $task->size ?? '';
        $this->loadTaskSchedule($task);
        $this->isPrivate = $task->is_private;
        $this->descriptionMode = 'preview';
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->reset('title', 'description', 'category', 'priority', 'size', 'startDate', 'startTime', 'endDate', 'endTime', 'estimatedTime', 'isPrivate');
        $this->descriptionMode = 'write';
    }

    public function save(): void
    {
        if (blank(trim($this->title)) || ! $this->editingId) {
            return;
        }

        $schedule = $this->taskScheduleData();
        if (! $schedule) {
            return;
        }

        $task = Task::find($this->editingId);

        if ($task) {
            $task->update([
                'title' => trim($this->title), 'description' => $this->description ?: null,
                'category' => $this->category ?: null, 'priority' => $this->priority ?: null,
                'size' => $this->size ?: null, ...$schedule, 'is_private' => $this->isPrivate,
            ]);
        }

        $this->closeForm();
    }

    public function render()
    {
        $today = today()->startOfDay();
        $columns = collect(['overdue', 'today', 'tomorrow', 'future', 'no-date'])
            ->mapWithKeys(fn (string $key) => [$key => collect()]);

        $this->applyTaskFilters(Task::query())->where('completed', false)->get()->each(function (Task $task) use ($columns, $today): void {
            $scheduledDate = ($task->start_date ?? $task->end_date)?->copy()->startOfDay();

            if (! $scheduledDate) {
                $columns['no-date']->push($task);
            } elseif ($scheduledDate->lt($today)) {
                $columns['overdue']->push($task);
            } elseif ($scheduledDate->isSameDay($today)) {
                $columns['today']->push($task);
            } elseif ($scheduledDate->isSameDay($today->copy()->addDay())) {
                $columns['tomorrow']->push($task);
            } else {
                $columns['future']->push($task);
            }
        });

        foreach (['overdue', 'today', 'tomorrow', 'future'] as $key) {
            $columns[$key] = $columns[$key]->sortBy(fn (Task $task) => ($task->start_date ?? $task->end_date)->timestamp)->values();
        }
        $columns['no-date'] = $columns['no-date']->sortByDesc('created_at')->values();

        return view('livewire.task.task-planning', compact('columns'));
    }
}
