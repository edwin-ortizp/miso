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
        // CHG-136: el diálogo no usa la lista paginada de Ajustes; aquí solo se arma lo que muestra el formulario.
        return view('livewire.home.panel.habit-editor', [
            'timesOfDay' => self::TIMES_OF_DAY,
            'actionOptions' => $this->registry()->grouped(),
            'actionFields' => $this->currentFields(),
            'actionModes' => \App\Models\HabitAction::MODES,
            'registry' => $this->registry(),
        ]);
    }
}
