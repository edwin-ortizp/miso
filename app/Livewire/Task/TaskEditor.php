<?php

namespace App\Livewire\Task;

use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\TaskStatus;
use App\Services\TaskCodeService;
use App\Services\TaskGamificationService;
use App\Services\TaskRecurrenceService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskEditor extends TaskList
{
    public $statusQuery = 'pending';
    public $filter = [];
    public $categoryFilter = [];
    public string $priorityFilter = '';
    public $dateFilter = [];
    public string $sizeFilter = '';
    public string $search = '';
    public ?string $editTask = null;
    public bool $expanded = false;
    #[\Livewire\Attributes\Locked]
    public ?string $recurringSnapshot = null;
    #[\Livewire\Attributes\Locked]
    public string $draftTaskId = '';
    #[\Livewire\Attributes\Locked]
    public string $editorIntent = '';

    public function mount(): void
    {
        $this->draftTaskId = (string) \Illuminate\Support\Str::uuid();
        if ($this->editTask) $this->openForm($this->editTask);
    }

    public function openForm(?string $id = null)
    {
        abort_unless(auth()->check(), 403);
        $task = $id ? Task::where('user_id', auth()->id())->findOrFail($id) : null;
        parent::openForm($id);
        $this->editorIntent = '';
        if ($task) $this->statusId = (string) ($task->status_id ?? '');
    }

    public function openScheduleForm(string $id): void
    {
        $this->openForm($id);
        $this->editorIntent = 'schedule';
    }

    public function save(): void
    {
        $data = $this->validatedDraft();
        if ($data === null) return;

        $task = $this->editingId ? Task::where('user_id', auth()->id())->findOrFail($this->editingId) : null;
        $status = $this->statusId !== '' ? TaskStatus::where('user_id', auth()->id())->findOrFail($this->statusId) : null;
        if ((! $task || ! $task->completed) && $data['is_recurrent'] && $status?->type === TaskStatus::DONE) {
            $proposal = $task ? clone $task : new Task();
            $proposal->fill($data);
            $this->recurringSnapshot = $task ? $this->recurrenceSnapshot($task) : 'new';
            $this->recurringTaskId = $task?->id;
            $this->nextOccurrenceDate = app(TaskRecurrenceService::class)->suggestedNextDate($proposal)->toDateString();
            $this->showRecurringCompletion = true;
            return;
        }
        $this->persistDraft($data, $status);
    }

    public function cancelRecurringCompletion(): void
    {
        parent::cancelRecurringCompletion();
        $this->recurringSnapshot = null;
    }

    public function confirmRecurringCompletion(TaskGamificationService $gamification): ?array
    {
        $this->validate(['nextOccurrenceDate' => ['required', 'date']]);
        $data = $this->validatedDraft();
        if ($data === null) return null;
        $status = TaskStatus::where('user_id', auth()->id())->findOrFail($this->statusId);
        if ($status->type !== TaskStatus::DONE || ! $data['is_recurrent'] || ! $this->recurringSnapshot || $this->recurringTaskId !== $this->editingId) {
            throw ValidationException::withMessages(['statusId' => 'La confirmación cambió. Revisa la tarea e inténtalo de nuevo.']);
        }
        $result = $this->persistDraft($data, $status, $gamification);
        $this->cancelRecurringCompletion();
        $this->recurringSnapshot = null;
        return $result;
    }

    private function validatedDraft(): ?array
    {
        abort_unless(auth()->check(), 403);
        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['string'], 'category' => ['string'], 'statusId' => ['string'],
            'priority' => ['string', \Illuminate\Validation\Rule::in(['', ...array_keys($this->priorities)])],
            'size' => ['string', \Illuminate\Validation\Rule::in(['', ...array_keys($this->sizes)])],
            'estimatedTime' => ['nullable', 'integer', 'min:0'],
            'startDate' => ['nullable', 'date_format:Y-m-d'], 'endDate' => ['nullable', 'date_format:Y-m-d'],
            'startTime' => ['nullable', 'date_format:H:i'], 'endTime' => ['nullable', 'date_format:H:i'],
            'recurrenceIntervalDays' => ['integer', 'min:1'],
        ]);
        if ($this->category !== '' && ! TaskCategory::where('user_id', auth()->id())->where('key', $this->category)->exists()) {
            throw ValidationException::withMessages(['category' => 'Selecciona una categoría propia.']);
        }
        if ($this->statusId !== '' && ! TaskStatus::where('user_id', auth()->id())->whereKey($this->statusId)->exists()) {
            throw ValidationException::withMessages(['statusId' => 'Selecciona un estado propio.']);
        }
        foreach ($this->relations as $i => $row) {
            $type = $row['type'] ?? '';
            $id = $row['id'] ?? '';
            if ($id === '') continue;
            $model = isset(self::RELATION_MODULES[$type]) ? Relation::getMorphedModel($type) : null;
            if (! $model || ! $model::query()->where('user_id', auth()->id())->whereKey($id)->exists()) {
                $this->relations[$i] = ['type' => '', 'id' => ''];
            }
        }
        if ($this->editorIntent === 'schedule' && ! filled($this->startDate) && ! filled($this->endDate)) {
            throw ValidationException::withMessages(['endDate' => 'Indica una fecha para programar la tarea.']);
        }
        $estimate = $this->estimatedTime;
        $schedule = $this->taskScheduleData();
        if ($schedule === null) return null;
        $this->estimatedTime = $estimate;
        $schedule['estimated_time'] = $estimate ?: null;
        $this->validateReminders($this->endDate, $this->endTime);
        $existing = $this->editingId ? Task::where('user_id', auth()->id())->findOrFail($this->editingId) : null;
        return [
            'title' => trim($this->title), 'description' => $this->description ?: null,
            'category' => $this->category ?: null, 'priority' => $this->priority ?: null,
            'size' => $this->size ?: null, ...$schedule, 'is_private' => $this->isPrivate,
            'is_recurrent' => $this->isRecurrent,
            'recurrence' => $this->isRecurrent
                ? ($this->nativeRecurrenceRule !== '' && $existing ? $existing->recurrence : app(TaskRecurrenceService::class)->buildRecurrence('custom', $this->recurrenceIntervalDays))
                : null,
        ];
    }

    private function recurrenceSnapshot(Task $task): string
    {
        return hash('sha256', json_encode([$task->id, $task->completed, $task->status_id, $task->is_recurrent, $task->recurrence, $task->start_date, $task->end_date], JSON_THROW_ON_ERROR));
    }

    private function persistDraft(array $data, ?TaskStatus $status, ?TaskGamificationService $recurrence = null): ?array
    {
        $result = DB::transaction(function () use ($data, $status, $recurrence) {
            app(TaskCodeService::class)->lock(auth()->id());
            $task = $this->editingId ? Task::where('user_id', auth()->id())->lockForUpdate()->findOrFail($this->editingId) : null;
            if (! $this->editingId && Task::withoutGlobalScopes()->whereKey($this->draftTaskId)->exists()) {
                return [$this->draftTaskId, null];
            }
            if ($recurrence && (($task && ! hash_equals($this->recurringSnapshot, $this->recurrenceSnapshot($task))) || (! $task && $this->recurringSnapshot !== 'new'))) {
                throw ValidationException::withMessages(['statusId' => 'La tarea cambió. Cierra la confirmación y revisa sus datos.']);
            }
            if ($task) $task->update($data);
            else {
                $task = new Task($data);
                $task->id = $this->draftTaskId;
                $task->user_id = auth()->id();
                $task->task_code = rand(10000, 99999);
                $task->save();
            }
            $this->syncTaskRelations($task);
            $this->saveReminders($task);
            $result = null;
            if ($status) {
                $gamification = app(TaskGamificationService::class);
                if ($status->type === TaskStatus::DONE && ! $task->completed) {
                    $result = $recurrence
                        ? app(TaskRecurrenceService::class)->completeAndSchedule($task, Carbon::parse($this->nextOccurrenceDate), $gamification)
                        : $gamification->complete($task);
                } elseif ($status->type !== TaskStatus::DONE && $task->completed) {
                    $gamification->reopen($task);
                }
                if (! $recurrence) $task->update(['status_id' => $status->id]);
            }
            return [$task->id, $result];
        }, 3);
        [$id, $feedback] = $result;
        $this->draftTaskId = (string) \Illuminate\Support\Str::uuid();
        $this->openForm($id);
        $this->dispatch('task-records-changed')->to(TaskList::class);
        $this->dispatch('task-calendar-refresh');
        $this->dispatch('task-editor-saved');
        if ($feedback['completed'] ?? false) $this->dispatch('task-completed', ...$feedback);
        return $feedback;
    }

    protected function formDefaults(): array
    {
        $fields = ['editingId', 'title', 'description', 'descriptionMode', 'category', 'statusId', 'priority', 'size', 'startDate', 'startTime', 'endDate', 'endTime', 'estimatedTime', 'isPrivate', 'isRecurrent', 'recurrenceIntervalDays', 'nativeRecurrenceRule', 'bulkTitles', 'editTask', 'relations', 'reminders'];

        return array_intersect_key(get_class_vars(self::class), array_flip($fields));
    }

    #[\Livewire\Attributes\On('focus-session-saved')]
    public function refreshFocusSummary(): void {}

    public function render()
    {
        abort_unless(auth()->check(), 403);
        $focusAvailable = \Illuminate\Support\Facades\Schema::hasColumn('pomodoro_sessions', 'task_id');
        return view('livewire.task.task-editor', [
            'defaults' => $this->formDefaults(),
            'statusOptions' => \App\Models\TaskStatus::ensureFor(auth()->id())
                ->pluck('name', 'id')->all(),
            'focusAvailable' => $focusAvailable,
            'focusSummary' => $focusAvailable && $this->editingId ? \App\Models\PomodoroSession::where('user_id', auth()->id())->where('task_id', $this->editingId)->where('completed', true)->selectRaw('count(*) as sessions, coalesce(sum(duration), 0) as seconds')->first() : null,
            'relationModules' => self::RELATION_MODULES,
            'relationOptions' => $this->relationOptions(),
        ]);
    }

    public function closeForm()
    {
        parent::closeForm();
        $this->dispatch('task-records-changed')->to(TaskList::class);
        $this->dispatch('task-calendar-refresh');
    }
}
