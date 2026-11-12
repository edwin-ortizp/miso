<?php

namespace App\Livewire\Onboarding;

use App\Livewire\Habit\HabitSettings;
use App\Models\HabitDefinition;
use App\Services\Experience\PersonalExperience;
use Livewire\Attributes\On;

class HabitEditor extends HabitSettings
{
    #[On('onboarding-habit')]
    public function openGuidedHabit(): void
    {
        $state = app(PersonalExperience::class)->state((int) auth()->id());
        $this->openForm($state['habit_id'] ?? null);
        if (! $this->editingId) {
            $this->name = in_array($state['context'] ?? '', ['study', 'both'], true) ? 'Estudiar con concentración' : 'Avanzar en mi meta personal';
            $this->timeOfDay = 'anytime';
            $this->goalDuration = '10 min';
            $this->icon = '🌱';
        }
    }

    public function save(): void
    {
        app(PersonalExperience::class)->change((int) auth()->id(), function ($state) {
            abort_unless(($state['step'] ?? 0) === 3, 403);
            $this->editingId = $state['habit_id'] ?? null;
            $name = trim($this->name);
            $time = $this->timeOfDay;
            parent::save();
            $state['habit_id'] = HabitDefinition::where('user_id', auth()->id())->where('name', $name)->where('time_of_day', $time)->firstOrFail()->id;
            $state['events']['habit_created'] ??= now()->toIso8601String();

            return $state;
        });
        $this->dispatch('onboarding-created');
    }

    public function render()
    {
        return view('livewire.home.panel.habit-editor', parent::render()->getData());
    }
}
