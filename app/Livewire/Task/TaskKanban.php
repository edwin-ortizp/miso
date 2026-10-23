<?php

namespace App\Livewire\Task;

use App\Livewire\Concerns\HandlesRecurringTaskCompletion;
use App\Livewire\Task\Concerns\FiltersTasks;
use App\Livewire\Task\Concerns\LoadsTaskCategories;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Services\TaskGamificationService;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

#[Layout('layouts.app')]
#[Title('Kanban')]
class TaskKanban extends Component
{
    use FiltersTasks, HandlesRecurringTaskCompletion, LoadsTaskCategories;

    #[Url(as: 'group', history: true)]
    public string $groupBy = 'state';

    public function mount(): void
    {
        $this->updatedGroupBy();
    }

    public function updatedGroupBy(): void
    {
        if (! in_array($this->groupBy, ['state', 'priority'], true)) {
            $this->groupBy = 'state';
        }
    }

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

    public function moveStatus(string $id, string $direction, TaskGamificationService $gamification): void
    {
        abort_unless(auth()->check(), 403);
        abort_unless(in_array($direction, ['previous', 'next'], true), 422);
        $task = Task::where('user_id', auth()->id())->findOrFail($id);
        $statuses = TaskStatus::where('user_id', auth()->id())->ordered()->get();
        $index = $statuses->search(fn (TaskStatus $status) => $status->id === $task->status_id);
        $target = $index === false ? null : $statuses->get($index + ($direction === 'next' ? 1 : -1));
        if (! $target || $this->showRecurringCompletion) {
            return;
        }

        if ($target->type === TaskStatus::DONE) {
            $this->toggleComplete($task->id, $gamification);
            return;
        }

        if ($task->completed) {
            $gamification->reopen($task);
        }
        $task->update(['status_id' => $target->id]);
    }

    public function render()
    {
        $tasks = $this->applyTaskFilters(Task::query())->orderByDesc('created_at')->get();

        $statuses = TaskStatus::where('user_id', auth()->id())->ordered()->get();
        $statusSteps = [];
        foreach ($statuses as $index => $status) {
            $statusSteps[$status->id] = ['current' => $status, 'previous' => $statuses->get($index - 1), 'next' => $statuses->get($index + 1)];
        }

        $columns = [
            'urgent-important' => ['label' => 'Urgente + Importante', 'color' => 'danger', 'tasks' => collect()],
            'not-urgent-important' => ['label' => 'No Urgente + Importante', 'color' => 'warning', 'tasks' => collect()],
            'urgent-not-important' => ['label' => 'Urgente + No Importante', 'color' => 'info', 'tasks' => collect()],
            'not-urgent-not-important' => ['label' => 'No Urgente + No Importante', 'color' => 'secondary', 'tasks' => collect()],
        ];

        if ($this->groupBy === 'state') {
            $columns = $statuses->mapWithKeys(fn (TaskStatus $status) => [$status->id => [
                    'label' => $status->name,
                    'color' => match ($status->type) {
                        TaskStatus::READY => 'warning',
                        TaskStatus::IN_PROGRESS => 'info',
                        TaskStatus::DONE => 'success',
                        default => 'secondary',
                    },
                    'tasks' => collect(),
                ]])->all();
        }

        foreach ($tasks as $task) {
            if ($this->groupBy === 'state') {
                $key = $task->status_id;
                if (! isset($columns[$key])) {
                    $key = 'without-status';
                    $columns[$key] ??= ['label' => 'Sin estado', 'color' => 'secondary', 'tasks' => collect()];
                }
                $columns[$key]['tasks']->push($task);
                continue;
            }

            $priority = $task->priority ?? 'not-urgent-not-important';
            if (isset($columns[$priority])) {
                $columns[$priority]['tasks']->push($task);
            } else {
                $columns['not-urgent-not-important']['tasks']->push($task);
            }
        }

        return view('livewire.task.task-kanban', [
            'columns' => $columns,
            'statusSteps' => $statusSteps,
        ]);
    }
}
