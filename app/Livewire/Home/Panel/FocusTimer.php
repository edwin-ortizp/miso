<?php

namespace App\Livewire\Home\Panel;

use App\Livewire\Pomodoro\PomodoroTimer;
use Livewire\Attributes\On;

class FocusTimer extends PomodoroTimer
{
    public bool $taskHost = false;
    #[\Livewire\Attributes\Locked]
    public ?string $manualTaskTitle = null;

    public function mount(bool $taskHost = false): void
    {
        $this->taskHost = $taskHost;
        parent::mount();
    }

    #[On('task-focus-manual')]
    public function openTaskManual(string $taskId, ?int $durationMinutes = null): void
    {
        if (! $this->taskHost) return;
        $task = \App\Models\Task::where('user_id', auth()->id())->findOrFail($taskId);
        $this->openForm();
        $this->manualTaskId = $task->id;
        $this->manualTaskTitle = $task->title;
        if ($durationMinutes && $durationMinutes > 0 && $durationMinutes <= 960) {
            $end = now()->startOfMinute();
            $this->manualStart = $end->copy()->subMinutes($durationMinutes)->format('Y-m-d\TH:i');
            $this->manualEnd = $end->format('Y-m-d\TH:i');
        }
    }

    public function boot(): void
    {
        abort_unless(auth()->check(), 403);
    }

    public function focusTask(?string $taskId): ?array
    {
        if (! $taskId) {
            return null;
        }

        $task = \App\Models\Task::query()->where('user_id', auth()->id())->find($taskId);

        return $task ? [
            'id' => (string) $task->id,
            'title' => $task->title,
            'html' => (string) \Illuminate\Support\Str::markdown($task->description ?? '', [
                'html_input' => 'strip', 'allow_unsafe_links' => false,
            ]),
            'hasDescription' => filled($task->description),
        ] : null;
    }

    public function saveSession(int $startedAt, int $endedAt, string $description, string $clientToken, ?string $taskId = null): void
    {
        parent::saveSession($startedAt, $endedAt, $description, $clientToken, $taskId);
        $this->dispatch('panel-changed');
    }

    public function render()
    {
        return view('livewire.home.panel.focus-timer', [
            'manualDurationSeconds' => $this->manualDurationSeconds(),
        ]);
    }

    #[On('panel-focus-manual')]
    public function openManualForm(): void
    {
        if (! $this->taskHost) $this->openForm();
    }

    public function saveManualSession(): void
    {
        // El panel solo crea registros; nunca edita una sesión recibida del cliente.
        $this->editingId = null;
        parent::saveManualSession();

        if (! $this->showForm) {
            $this->dispatch('panel-changed');
        }
    }

    protected function manualUsesToday(): bool
    {
        return ! $this->taskHost;
    }
}
