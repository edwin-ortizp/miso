<?php

namespace App\Livewire\Home\Panel;

use App\Livewire\Pomodoro\PomodoroTimer;
use Livewire\Attributes\On;

class FocusTimer extends PomodoroTimer
{
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

    public function saveSession(int $startedAt, int $endedAt, string $description, string $clientToken): void
    {
        parent::saveSession($startedAt, $endedAt, $description, $clientToken);
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
        $this->openForm();
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
        return true;
    }
}
