<?php

namespace App\Livewire\Onboarding;

use App\Livewire\Goal\GoalIndex;
use App\Services\Experience\PersonalExperience;
use Livewire\Attributes\On;

class GoalEditor extends GoalIndex
{
    #[On('onboarding-goal')]
    public function openGuidedGoal(): void
    {
        $state = app(PersonalExperience::class)->state((int) auth()->id());
        $this->openForm($state['goal_id'] ?? null);
        if (! $this->editingId) {
            $this->title = $state['intention'] ?? '';
        }
    }

    public function save()
    {
        app(PersonalExperience::class)->change((int) auth()->id(), function ($state) {
            abort_unless(($state['step'] ?? 0) === 4, 403);
            $this->editingId = $state['goal_id'] ?? null;
            $savedId = parent::save();
            $state['modules'] = array_values(array_unique([...$state['modules'], 'goals']));
            $state['goal_id'] = $savedId;
            $state['events']['goal_created'] ??= now()->toIso8601String();

            return $state;
        });
        $this->dispatch('onboarding-created');
    }

    public function render()
    {
        return view('livewire.onboarding.goal-editor');
    }
}
