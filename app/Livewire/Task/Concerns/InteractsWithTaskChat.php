<?php

namespace App\Livewire\Task\Concerns;

use App\Models\Task;
use App\Services\TaskGamificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

trait InteractsWithTaskChat
{
    protected function taskChatActions(): array
    {
        $voice = config('companion_chat.tasks');
        $compose = fn (bool $bulk) => [
            'type' => 'compose', 'id' => $bulk ? 'task-bulk' : 'task-single',
            'label' => $voice[$bulk ? 'bulk_label' : 'create_label'], 'icon' => $bulk ? 'bi-list-task' : 'bi-check2-square',
            'mode' => $bulk ? 'bulk' : 'single', 'call' => 'quickCreateTasks',
            'placeholder' => $voice[$bulk ? 'bulk_placeholder' : 'single_placeholder'],
            'prompt' => $voice[$bulk ? 'bulk_prompt' : 'single_prompt'], 'submit' => $voice['create_label'],
            'submitMany' => 'Crear {n} tareas', 'reply' => $voice['reply'], 'replyMany' => $voice['reply_many'],
            'fields' => ['category', 'when'], 'options' => $this->categories ?: null, 'whenLabels' => $voice['when'],
            'keywords' => $voice['keywords'][$bulk ? 'bulk' : 'single'],
            'more' => ['event' => $bulk ? 'task-bulk-editor' : 'task-editor', 'detail' => ['action' => 'openForm'], 'field' => $bulk ? 'bulkTitles' : 'title'],
        ];
        $view = ['type' => 'step', 'id' => 'task-view', 'label' => $voice['view_label'], 'icon' => 'bi-eye', 'then' => 'view', 'say' => $voice['view_question'], 'keywords' => $voice['keywords']['view']];
        $sort = ['type' => 'step', 'id' => 'task-sort', 'label' => $voice['sort_label'], 'icon' => 'bi-sort-down', 'then' => 'sort', 'say' => $voice['sort_question'], 'keywords' => $voice['keywords']['sort']];
        $home = ['type' => 'step', 'label' => $voice['home_label'], 'icon' => 'bi-arrow-return-left', 'then' => 'start'];
        $single = $compose(false);
        $bulk = $compose(true);
        $actions = [$single, $bulk, $view, $sort,
            ['type' => 'tap', 'id' => 'task-next', 'label' => $voice['next_label'], 'icon' => 'bi-signpost', 'call' => 'chatNext', 'reply' => $voice['next_reply'], 'then' => 'next', 'emptyThen' => 'start', 'keywords' => $voice['keywords']['next']],
        ];
        foreach ([...self::FILTER_DATES, '' => 'Todas'] as $key => $label) {
            $actions[] = ['type' => 'tap', 'id' => 'task-view-'.$key, 'group' => 'view', 'label' => $label, 'call' => 'chatFilter', 'args' => [$key], 'reply' => $voice['view_reply'], 'emptyReply' => $voice['view_empty'], 'then' => 'after-view', 'keywords' => [$label]];
        }
        foreach (self::SORTS as $key => $label) {
            $actions[] = ['type' => 'tap', 'id' => 'task-sort-'.$key, 'group' => 'sort', 'label' => $label, 'call' => 'chatSort', 'args' => [$key], 'reply' => $voice['sort_reply'], 'then' => 'after-view', 'keywords' => [$label]];
        }
        $actions[] = $sort + ['group' => 'after-view'];
        $actions[] = ['type' => 'tap', 'group' => 'after-view', 'label' => $voice['clear_label'], 'call' => 'chatFilter', 'args' => [''], 'reply' => $voice['view_reply'], 'then' => 'after-view'];
        $actions[] = $single + ['group' => 'after-view'];
        $actions[] = ['type' => 'tap', 'group' => 'next', 'label' => $voice['complete_label'], 'icon' => 'bi-check2-circle', 'call' => 'chatComplete', 'args' => ['{id}'], 'reply' => $voice['completed_reply'], 'then' => 'after-view'];
        $actions[] = ['type' => 'tap', 'group' => 'next', 'label' => $voice['open_label'], 'icon' => 'bi-box-arrow-up-right', 'call' => 'chatOpen', 'args' => ['{id}'], 'reply' => '', 'event' => 'task-editor', 'detail' => ['action' => 'openForm', 'id' => '{id}']];
        foreach (['view', 'sort', 'after-view', 'next'] as $group) {
            $actions[] = $home + ['group' => $group];
        }

        return $actions;
    }

    protected function chatTasksQuery(): Builder
    {
        abort_unless(auth()->check(), 403);
        $this->normalizeFilters();

        return $this->applyTaskFilters(Task::query()->where('user_id', auth()->id()));
    }

    public function chatFilter(string $date): array
    {
        if ($date !== '' && ! array_key_exists($date, self::FILTER_DATES)) {
            return ['ok' => false, 'message' => config('companion_chat.tasks.invalid_filter')];
        }

        $this->dateFilter = $date === '' ? [] : [$date];
        $this->resetPage();
        $total = $this->chatTasksQuery()->count();

        return ['ok' => true, 'total' => $total, 'label' => self::FILTER_DATES[$date] ?? 'Todas'];
    }

    public function chatSort(string $sort): array
    {
        if (! array_key_exists($sort, self::SORTS)) {
            return ['ok' => false, 'message' => config('companion_chat.tasks.invalid_sort')];
        }

        $this->sort = $sort;
        $this->resetPage();

        return ['ok' => true, 'total' => $this->chatTasksQuery()->count(), 'label' => self::SORTS[$sort]];
    }

    public function chatNext(): array
    {
        $overdue = Task::query()->where('user_id', auth()->id())->overdue()->select('tasks.id');
        $today = today()->toDateString();
        $task = $this->chatTasksQuery()->where('completed', false)
            ->orderByRaw(
                'CASE WHEN tasks.id IN ('.$overdue->toSql().') THEN 0 WHEN DATE(start_date) = ? OR DATE(end_date) = ? THEN 1 ELSE 2 END',
                [...$overdue->getBindings(), $today, $today],
            )
            ->orderByRaw("CASE priority WHEN 'urgent-important' THEN 1 WHEN 'not-urgent-important' THEN 2 WHEN 'urgent-not-important' THEN 3 ELSE 4 END")
            ->chronological()->orderBy('tasks.id')->first();

        return $task
            ? ['ok' => true, 'id' => $task->id, 'title' => $task->title]
            : ['ok' => true, 'empty' => true, 'message' => config('companion_chat.tasks.next_empty')];
    }

    public function chatOpen(string $id): array
    {
        $task = $this->chatTasksQuery()->where('completed', false)->find($id);

        return $task
            ? ['ok' => true, 'id' => $task->id, 'title' => $task->title]
            : ['ok' => false, 'message' => config('companion_chat.tasks.next_stale')];
    }

    public function chatComplete(string $id, TaskGamificationService $gamification): array
    {
        return DB::transaction(function () use ($id, $gamification): array {
            $task = $this->chatTasksQuery()->where('completed', false)->lockForUpdate()->find($id);
            if (! $task) {
                return ['ok' => false, 'message' => config('companion_chat.tasks.next_stale')];
            }

            $this->completeTask($id, $gamification);
            if ($this->showRecurringCompletion && $this->recurringTaskId === $id) {
                return ['ok' => true, 'pending' => true, 'message' => config('companion_chat.tasks.recurring_confirmation')];
            }

            return $task->refresh()->completed
                ? ['ok' => true, 'message' => config('companion_chat.tasks.completed_reply')]
                : ['ok' => false, 'message' => config('companion_chat.tasks.next_stale')];
        });
    }
}
