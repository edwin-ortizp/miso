<?php

namespace App\Livewire\Task;

use App\Livewire\Task\Concerns\LoadsTaskCategories;
use App\Livewire\Concerns\HandlesRecurringTaskCompletion;
use App\Livewire\Concerns\InteractsWithTaskSchedule;
use App\Models\Task;
use App\Services\TaskGamificationService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Flujo de tareas')]
class TaskFlow extends Component
{
    use LoadsTaskCategories;
    use HandlesRecurringTaskCompletion, InteractsWithTaskSchedule;

    private const INITIAL_LANE_LIMIT = 30;

    public array $visibleLimits = [];

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

    public bool $completed = false;


    public array $priorities = [
        'urgent-important' => '🔴 Urgente e Importante',
        'not-urgent-important' => '🟡 No Urgente pero Importante',
        'urgent-not-important' => '🟠 Urgente No Importante',
        'not-urgent-not-important' => '⚪ No Urgente No Importante',
    ];

    public array $sizes = [
        'XS' => 'XS (< 30 min)',
        'S' => 'S (30 min - 1h)',
        'M' => 'M (1-2h)',
        'L' => 'L (2-4h)',
        'XL' => 'XL (> 4h)',
    ];

    public function movePrevious(string $id): void
    {
        $this->swapWithAdjacentTask($id, 'previous');
    }

    public function moveNext(string $id): void
    {
        $this->swapWithAdjacentTask($id, 'next');
    }

    public function loadMore(string $laneKey): void
    {
        $this->visibleLimits[$laneKey] = $this->laneLimit($laneKey) + self::INITIAL_LANE_LIMIT;
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
        $this->completed = $task->completed;
        $this->descriptionMode = 'preview';
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->reset('title', 'description', 'category', 'priority', 'size', 'startDate', 'startTime', 'endDate', 'endTime', 'estimatedTime', 'isPrivate', 'completed');
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
                'title' => trim($this->title),
                'description' => $this->description ?: null,
                'category' => $this->category ?: null,
                'priority' => $this->priority ?: null,
                'size' => $this->size ?: null,
                ...$schedule,
                'is_private' => $this->isPrivate,
            ]);
        }

        $this->closeForm();
    }

    public function toggleComplete(string $id, TaskGamificationService $gamification): void
    {
        $task = Task::find($id);
        if (! $task) {
            return;
        }

        if ($this->prepareRecurringCompletion($task)) {
            return;
        }

        $result = $gamification->toggle($task);
        if ($this->editingId === $task->id) {
            $this->completed = $result['completed'];
        }

        if ($result['completed']) {
            $this->dispatch('task-completed', ...$result);
        }
    }

    public function render()
    {
        $lanes = collect($this->categories)
            ->map(fn (string $label, string $key) => $this->buildLane($key, $label))
            ->filter()
            ->values();

        $uncategorized = $this->buildLane(null, 'Sin categoría', 'uncategorized');
        if ($uncategorized) {
            $lanes->push($uncategorized);
        }

        return view('livewire.task.task-flow', ['lanes' => $lanes]);
    }

    private function buildLane(?string $category, string $label, ?string $laneKey = null): ?array
    {
        $laneKey ??= $category;
        $categoryTasks = $this->tasksForCategory($category);
        $pendingTasks = (clone $categoryTasks)->where('completed', false);
        $completedTasks = (clone $categoryTasks)->where('completed', true);
        $pendingTotal = (clone $pendingTasks)->count();
        $completedTotal = (clone $completedTasks)->count();

        if ($pendingTotal === 0 && $completedTotal === 0) {
            return null;
        }

        $tasks = $pendingTasks
            ->orderBy('flow_position')
            ->orderBy('id')
            ->limit($this->laneLimit($laneKey))
            ->get();
        $completedHistory = $completedTasks
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->get();

        return compact('category', 'label', 'laneKey', 'tasks', 'pendingTotal', 'completedTotal', 'completedHistory');
    }

    private function laneLimit(string $laneKey): int
    {
        return max(self::INITIAL_LANE_LIMIT, (int) ($this->visibleLimits[$laneKey] ?? self::INITIAL_LANE_LIMIT));
    }

    private function tasksForCategory(?string $category)
    {
        $query = Task::query();

        return $category === null ? $query->whereNull('category') : $query->where('category', $category);
    }

    private function swapWithAdjacentTask(string $id, string $direction): void
    {
        DB::transaction(function () use ($id, $direction): void {
            $task = Task::query()->lockForUpdate()->find($id);
            if (! $task || $task->completed) {
                return;
            }

            $lane = $this->tasksForCategory($task->category)
                ->where('completed', false)
                ->lockForUpdate();
            $neighbor = $direction === 'previous'
                ? $lane->where('flow_position', '<', $task->flow_position)->orderByDesc('flow_position')->orderByDesc('id')->first()
                : $lane->where('flow_position', '>', $task->flow_position)->orderBy('flow_position')->orderBy('id')->first();

            if (! $neighbor) {
                return;
            }

            [$task->flow_position, $neighbor->flow_position] = [$neighbor->flow_position, $task->flow_position];
            $task->saveQuietly();
            $neighbor->saveQuietly();
        });
    }
}
