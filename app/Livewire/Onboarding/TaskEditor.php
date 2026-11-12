<?php

namespace App\Livewire\Onboarding;

use App\Services\Experience\PersonalExperience;
use Livewire\Attributes\On;

class TaskEditor extends \App\Livewire\Task\TaskEditor
{
    #[On('onboarding-task')]
    public function openGuidedTask(): void
    {
        $state = app(PersonalExperience::class)->state((int) auth()->id());
        $this->openForm($state['task_id'] ?? null);
    }

    public function save(): void
    {
        app(PersonalExperience::class)->change((int) auth()->id(), function ($state) {
            abort_unless(($state['step'] ?? 0) === 4, 403);
            $this->editingId = $state['task_id'] ?? null;
            // This step prepares a future action, never marks work as performed.
            $this->statusId = '';
            parent::save();
            if ($this->editingId) {
                $state['modules'] = array_values(array_unique([...$state['modules'], 'tasks']));
                $state['task_id'] = $this->editingId;
                $state['events']['task_created'] ??= now()->toIso8601String();
                $this->closeForm();
            }

            return $state;
        });
        $this->dispatch('onboarding-created');
    }
}
