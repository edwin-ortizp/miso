<?php

namespace App\Livewire\Task;

use App\Livewire\Task\Concerns\LoadsTaskCategories;
use App\Models\Task;
use App\Livewire\Concerns\InteractsWithTaskSchedule;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Gantt de tareas')]
class TaskGantt extends Component
{
    use LoadsTaskCategories;
    use InteractsWithTaskSchedule;
    public string $month;

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

    public array $sizes = [
        'XS' => 'XS (< 30 min)',
        'S' => 'S (30 min - 1h)',
        'M' => 'M (1-2h)',
        'L' => 'L (2-4h)',
        'XL' => 'XL (> 4h)',
    ];

    public function mount(): void
    {
        $this->month = now()->startOfMonth()->format('Y-m-d');
    }

    public function previousMonth(): void
    {
        $this->month = Carbon::parse($this->month)->subMonthNoOverflow()->startOfMonth()->format('Y-m-d');
    }

    public function nextMonth(): void
    {
        $this->month = Carbon::parse($this->month)->addMonthNoOverflow()->startOfMonth()->format('Y-m-d');
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

    public function render()
    {
        $monthStart = Carbon::parse($this->month)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $days = collect(CarbonPeriod::create($monthStart, $monthEnd));

        $scheduledTasks = Task::query()
            ->where(fn ($query) => $query->whereNotNull('start_date')->orWhereNotNull('end_date'))
            ->orderBy('start_date')
            ->orderBy('end_date')
            ->get()
            ->map(function (Task $task) {
                $start = ($task->start_date ?? $task->end_date)->copy()->startOfDay();
                $end = ($task->end_date ?? $task->start_date)->copy()->startOfDay();

                if ($end->lt($start)) {
                    [$start, $end] = [$end, $start];
                }

                return ['task' => $task, 'start' => $start, 'end' => $end];
            })
            ->filter(fn (array $item) => $item['end']->gte($monthStart) && $item['start']->lte($monthEnd))
            ->map(function (array $item) use ($monthStart, $monthEnd) {
                $visibleStart = $item['start']->max($monthStart);
                $visibleEnd = $item['end']->min($monthEnd);

                return [...$item, 'column' => $monthStart->diffInDays($visibleStart) + 1, 'span' => $visibleStart->diffInDays($visibleEnd) + 1];
            })
            ->values();

        $unscheduledTasks = Task::query()
            ->whereNull('start_date')
            ->whereNull('end_date')
            ->orderByDesc('created_at')
            ->get();

        return view('livewire.task.task-gantt', [
            'monthLabel' => $monthStart->locale('es')->translatedFormat('F Y'),
            'days' => $days,
            'scheduledTasks' => $scheduledTasks,
            'unscheduledTasks' => $unscheduledTasks,
        ]);
    }
}
