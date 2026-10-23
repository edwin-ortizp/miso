<?php

namespace App\Livewire\Home\Panel;

use App\Livewire\Pomodoro\PomodoroTimer;

class FocusTimer extends PomodoroTimer
{
    public function boot(): void
    {
        abort_unless(auth()->check(), 403);
    }

    public function saveSession(int $startedAt, int $endedAt, string $description, string $clientToken): void
    {
        parent::saveSession($startedAt, $endedAt, $description, $clientToken);
        $this->dispatch('panel-changed');
    }

    public function render()
    {
        return view('livewire.home.panel.focus-timer');
    }
}
