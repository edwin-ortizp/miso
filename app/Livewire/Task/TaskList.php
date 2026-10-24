<?php

namespace App\Livewire\Task;

use App\Livewire\Task\Concerns\InteractsWithTaskRelations;
use App\Livewire\Task\Concerns\FiltersTasks;
use App\Livewire\Task\Concerns\LoadsTaskCategories;
use App\Services\TaskRecurrenceService;
use App\Livewire\Concerns\HandlesRecurringTaskCompletion;
use App\Livewire\Concerns\InteractsWithTaskSchedule;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Services\TaskGamificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use App\Livewire\Concerns\WithManagementCard;

#[Layout('layouts.app')]
#[Title('Tareas')]
class TaskList extends Component
{
    use \App\Livewire\Concerns\HasReminderDraft;
    use InteractsWithTaskRelations, LoadsTaskCategories;
    use HandlesRecurringTaskCompletion, InteractsWithTaskSchedule;
    use WithManagementCard, FiltersTasks {
        FiltersTasks::removeFilter insteadof WithManagementCard;
        FiltersTasks::clearFilters insteadof WithManagementCard;
    }

    protected function defaultStatusFilter(): string
    {
        return 'pending';
    }

    /** Catálogo de estados memorizado durante la petición (siguiente paso de cada fila). */
    protected ?\Illuminate\Support\Collection $statusCatalog = null;

    public const SORTS = [
        'updated' => 'Última actualización',
        'chronological' => 'Cronológico',
        'priority' => 'Prioridad',
        'recent' => 'Más recientes',
        'title' => 'Título (A–Z)',
    ];

    #[Url(as: 'sort', history: true, except: 'updated')]
    public string $sort = 'updated';

    #[Url(as: 'edit')]
    public ?string $editTask = null;

    // Form
    public bool $showForm = false;

    public string $descriptionMode = 'write';

    public ?string $editingId = null;

    public string $title = '';

    public string $description = '';

    public string $category = '';

    public string $priority = '';

    /** Estado elegido en el editor (vacío = conservar o, al crear, el primer estado por hacer). */
    public string $statusId = '';

    public string $size = '';

    public ?string $startDate = null;

    public ?string $endDate = null;

    public ?int $estimatedTime = null;

    public bool $isPrivate = false;

    public bool $isRecurrent = false;

    public int $recurrenceIntervalDays = 7;

    public string $nativeRecurrenceRule = '';

    // Alta masiva: cada línea no vacía se convierte en una tarea con los demás
    // campos del formulario (categoría, prioridad, tamaño, fechas, privacidad).
    public string $bulkTitles = '';


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

    public function updatedSort(): void
    {
        $this->sort = array_key_exists($this->sort, self::SORTS) ? $this->sort : 'updated';
        $this->resetPage();
    }

    public function openForm(?string $id = null)
    {
        $this->resetForm();
        $this->descriptionMode = 'write';

        if ($id) {
            $task = Task::find($id);
            if ($task) {
                $this->editingId = $id;
                $this->descriptionMode = 'preview';
                $this->title = $task->title;
                $this->description = $task->description ?? '';
                $this->category = $task->category ?? '';
                $this->priority = $task->priority ?? '';
                $this->statusId = $task->status && $task->status->type !== \App\Models\TaskStatus::DONE ? $task->status_id : '';
                $this->size = $task->size ?? '';
                $this->loadTaskSchedule($task);
                $this->loadReminders($task);
                $this->isPrivate = $task->is_private;
                $this->isRecurrent = $task->is_recurrent;
                $this->recurrenceIntervalDays = max(1, (int) (($task->recurrence ?? [])['customDays'] ?? 7));
                $this->nativeRecurrenceRule = (string) (($task->recurrence ?? [])['rrule'] ?? '');
                // Una regla diaria simple precarga los días por si el usuario la cambia.
                if (preg_match('/^FREQ=DAILY;INTERVAL=(\d+)$/', $this->nativeRecurrenceRule, $match)) {
                    $this->recurrenceIntervalDays = max(1, (int) $match[1]);
                }
                $this->loadTaskRelations($task);
            }
        }

        $this->showForm = true;
    }

    public function closeForm()
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->editTask = null;
        $this->resetForm();
    }

    public function saveBulk(): void
    {
        $lines = collect(preg_split('/\R/u', $this->bulkTitles) ?: [])
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->values();

        if ($lines->isEmpty()) {
            $this->addError('bulkTitles', 'Escribe al menos una tarea.');

            return;
        }

        $schedule = $this->taskScheduleData();
        if (! $schedule) {
            return;
        }

        $baseData = [
            'description' => $this->description ?: null,
            'category' => $this->category ?: null,
            'priority' => $this->priority ?: null,
            'size' => $this->size ?: null,
            ...$schedule,
            'is_private' => $this->isPrivate,
        ];

        DB::transaction(function () use ($lines, $baseData) {
            foreach ($lines as $line) {
                $parsed = $this->parseObsidianLine($line);
                $data = array_filter($parsed['overrides'], fn ($v) => $v !== null) + $baseData;
                $data['title'] = $parsed['title'];
                $data['task_code'] = rand(10000, 99999);
                Task::create($data);
            }
        });

        $this->closeForm();
    }

    private function parseObsidianLine(string $line): array
    {
        $overrides = [];

        $priorityMap = [
            "\u{1F53A}" => 'urgent-important',
            "\u{1F53C}" => 'not-urgent-important',
            "\u{1F53D}" => 'not-urgent-not-important',
        ];

        foreach ($priorityMap as $emoji => $priority) {
            if (str_contains($line, $emoji)) {
                $overrides['priority'] = $priority;
                $line = str_replace($emoji, '', $line);
            }
        }

        $datePattern = '/([\x{1F4C5}\x{1F6EB}\x{231B}\x{2705}])\s*(\d{4}-\d{2}-\d{2})/u';

        if (preg_match_all($datePattern, $line, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $emoji = $match[1];
                $date = $match[2];

                match ($emoji) {
                    "\u{1F4C5}" => $overrides['end_date'] = Carbon::parse($date),
                    "\u{1F6EB}" => $overrides['start_date'] = Carbon::parse($date),
                    "\u{231B}" => $overrides['start_date'] ??= Carbon::parse($date),
                    "\u{2705}" => (function () use (&$overrides, $date) {
                        $overrides['completed'] = true;
                        $overrides['completed_at'] = Carbon::parse($date);
                    })(),
                    default => null,
                };
            }

            $line = preg_replace($datePattern, '', $line);
        }

        $title = trim(preg_replace('/\s+/', ' ', $line));

        return ['title' => $title, 'overrides' => $overrides];
    }

    public function save()
    {
        return DB::transaction(fn () => $this->persistTaskWithReminders());
    }

    private function persistTaskWithReminders()
    {
        if (empty(trim($this->title))) {
            return;
        }

        $schedule = $this->taskScheduleData();
        if (! $schedule) {
            return;
        }

        $data = [
            'title' => trim($this->title),
            'description' => $this->description ?: null,
            'category' => $this->category ?: null,
            'priority' => $this->priority ?: null,
            'size' => $this->size ?: null,
            ...$schedule,
            'is_private' => $this->isPrivate,
            'is_recurrent' => $this->isRecurrent,
            'recurrence' => $this->isRecurrent
                ? ($this->nativeRecurrenceRule !== '' && $this->editingId
                    ? Task::find($this->editingId)?->recurrence
                    : app(TaskRecurrenceService::class)->buildRecurrence('custom', $this->recurrenceIntervalDays))
                : null,
        ];

        $this->validateReminders($this->endDate, $this->endTime);

        // Solo estados no terminados: completar sigue pasando por la casilla (XP y recurrencias).
        if ($this->statusId !== '' && \App\Models\TaskStatus::whereKey($this->statusId)->where('type', '!=', \App\Models\TaskStatus::DONE)->exists()) {
            $data['status_id'] = $this->statusId;
        }

        if ($this->editingId) {
            $task = Task::find($this->editingId);
            $task?->update($data);
        } else {
            $data['task_code'] = rand(10000, 99999);
            $task = Task::create($data);
        }

        if ($task) {
            $this->syncTaskRelations($task);
            $this->saveReminders($task);
        }

        $this->closeForm();
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

    /**
     * Acción principal de la fila: avanza la tarea al siguiente tipo de estado del flujo
     * (por hacer → listo → en curso) y desde el último paso abierto la completa.
     */
    public function advanceStatus(string $id, TaskGamificationService $gamification): void
    {
        $task = Task::with('status')->find($id);
        if (! $task) {
            return;
        }

        $next = $task->completed ? null : $this->nextStatus($task->status);
        if (! $next) {
            $this->toggleComplete($id, $gamification);

            return;
        }

        $task->update(['status_id' => $next->id]);
    }

    /** Primer estado del usuario del siguiente tipo abierto del flujo; null si toca completar. */
    private function nextStatus(?TaskStatus $status): ?TaskStatus
    {
        $types = [TaskStatus::TODO, TaskStatus::READY, TaskStatus::IN_PROGRESS];
        $position = array_search($status?->type ?? TaskStatus::TODO, $types, true);
        if ($position === false) {
            return null;
        }

        $this->statusCatalog ??= TaskStatus::query()->where('user_id', auth()->id())->ordered()->get();
        foreach (array_slice($types, $position + 1) as $type) {
            if ($next = $this->statusCatalog->firstWhere('type', $type)) {
                return $next;
            }
        }

        return null;
    }

    /** Etiqueta del botón principal de la fila según el siguiente paso. */
    public function advanceLabel(Task $task): string
    {
        if ($task->completed) {
            return 'Reabrir';
        }

        $next = $this->nextStatus($task->status);

        return $next ? 'Pasar a '.$next->name : 'Completar';
    }

    public function delete(string $id)
    {
        // Por modelo, no masivo: así corren el observer de CalDAV y la limpieza de vínculos.
        Task::query()->find($id)?->delete();
    }

    private function resetForm()
    {
        $this->reminders = [];
        $this->title = '';
        $this->description = '';
        $this->category = '';
        $this->priority = '';
        $this->statusId = '';
        $this->size = '';
        $this->startDate = null;
        $this->startTime = null;
        $this->endDate = null;
        $this->endTime = null;
        $this->estimatedTime = null;
        $this->isPrivate = false;
        $this->isRecurrent = false;
        $this->relations = [['type' => '', 'id' => '']];
        $this->recurrenceIntervalDays = 7;
        $this->nativeRecurrenceRule = '';
        $this->descriptionMode = 'write';
        $this->bulkTitles = '';
        $this->resetValidation('bulkTitles');
    }

    #[\Livewire\Attributes\On('task-records-changed')]
    public function refreshRecords(): void {}

    public function render()
    {
        $query = $this->applyTaskFilters(Task::query()->with('status'));

        match ($this->sort) {
            'updated' => $query->orderByDesc('updated_at'),
            'priority' => $query->orderByRaw("CASE WHEN priority = 'urgent-important' THEN 1 WHEN priority = 'not-urgent-important' THEN 2 WHEN priority = 'urgent-not-important' THEN 3 ELSE 4 END"),
            'recent' => $query->orderByDesc('created_at'),
            'title' => $query->orderBy('title'),
            default => null,
        };

        $tasks = $query->chronological()
            ->paginate($this->perPage());

        // `CURDATE()` y `NOW()` no existen en SQLite: se pasan como parámetros.
        $today = today()->toDateString();

        $stats = Task::selectRaw('
            SUM(CASE WHEN completed = 0 THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN completed = 1 THEN 1 ELSE 0 END) as completed_count,
            SUM(CASE WHEN completed = 1 AND DATE(completed_at) = ? THEN 1 ELSE 0 END) as completed_today,
            SUM(CASE WHEN completed = 0 AND (DATE(start_date) = ? OR DATE(end_date) = ?) THEN 1 ELSE 0 END) as planned_today
        ', [$today, $today, $today])->first();

        $categoryBreakdown = Task::where('completed', true)
            ->whereDate('completed_at', today())
            ->whereNotNull('category')
            ->selectRaw('category, COUNT(*) as total')
            ->groupBy('category')
            ->pluck('total', 'category')
            ->toArray();

        $statusOptions = TaskStatus::query()->where('user_id', auth()->id())->ordered()->pluck('name', 'id')->all();

        return view('livewire.task.task-list', [
            'tasks' => $tasks,
            'sorts' => self::SORTS,
            'statusOptions' => $statusOptions,
            'activeFilters' => $this->taskFilterChips(),
            'pendingCount' => (int) $stats->pending_count,
            'completedCount' => (int) $stats->completed_count,
            'completedToday' => (int) $stats->completed_today,
            'plannedToday' => (int) $stats->planned_today,
            'overdueCount' => Task::query()->overdue()->count(),
            'categoryBreakdown' => $categoryBreakdown,
        ]);
    }
}
