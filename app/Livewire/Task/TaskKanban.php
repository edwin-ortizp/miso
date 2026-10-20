<?php

namespace App\Livewire\Task;

use App\Livewire\Concerns\HandlesRecurringTaskCompletion;
use App\Livewire\Task\Concerns\FiltersTasks;
use App\Livewire\Task\Concerns\LoadsTaskCategories;
use App\Models\Task;
use App\Services\TaskGamificationService;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Kanban')]
class TaskKanban extends Component
{
    use FiltersTasks, HandlesRecurringTaskCompletion, LoadsTaskCategories;

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

    public function render()
    {
        $tasks = $this->applyTaskFilters(Task::query())->orderByDesc('created_at')->get();

        $columns = [
            'urgent-important' => ['label' => 'Urgente + Importante', 'color' => 'danger', 'tasks' => collect()],
            'not-urgent-important' => ['label' => 'No Urgente + Importante', 'color' => 'warning', 'tasks' => collect()],
            'urgent-not-important' => ['label' => 'Urgente + No Importante', 'color' => 'info', 'tasks' => collect()],
            'not-urgent-not-important' => ['label' => 'No Urgente + No Importante', 'color' => 'secondary', 'tasks' => collect()],
        ];

        foreach ($tasks as $task) {
            $priority = $task->priority ?? 'not-urgent-not-important';
            if (isset($columns[$priority])) {
                $columns[$priority]['tasks']->push($task);
            } else {
                $columns['not-urgent-not-important']['tasks']->push($task);
            }
        }

        return view('livewire.task.task-kanban', [
            'columns' => $columns,
        ]);
    }
}
