<?php

namespace App\Livewire\Home\Panel;

use App\Livewire\Habit\HabitSettings;
use Livewire\Attributes\On;

class HabitEditor extends HabitSettings
{
    public function boot(): void
    {
        abort_unless(auth()->check(), 403);
    }

    #[On('panel-habit')]
    public function openPanelHabit(): void
    {
        $this->openForm();
    }

    public function save(): void
    {
        parent::save();
        if (! $this->showForm) {
            $this->dispatch('panel-changed');
        }
    }

    public function render()
    {
        return view('livewire.home.panel.habit-editor', parent::render()->getData());
    }
}
