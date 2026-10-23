<?php

namespace App\Livewire\Home\Panel;

use App\Livewire\Goal\GoalDetail;
use App\Models\Goal;
use App\Support\GoalProgress;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;

class GoalEditor extends GoalDetail
{
    #[Locked]
    public string $goalId = '';

    public function boot(): void
    {
        abort_unless(auth()->check(), 403);
    }

    public function mount(string $goal = ''): void {}

    #[On('panel-goal')]
    public function openPanelGoal(string $id): void
    {
        $goal = Goal::findOrFail($id);
        $this->goalId = $goal->id;
        $this->showEntryForm = $this->showNumericForm = false;
        GoalProgress::configuration($goal->numeric_goal) ? $this->openNumericForm() : $this->openEntryForm();
    }

    public function saveEntry(): void
    {
        parent::saveEntry();
        $this->dispatch('panel-changed');
    }

    public function saveNumericEntry(): void
    {
        parent::saveNumericEntry();
        $this->dispatch('panel-changed');
    }

    public function render()
    {
        return view('livewire.home.panel.goal-editor', ['kpi' => $this->goalId ? GoalProgress::configuration(Goal::findOrFail($this->goalId)->numeric_goal) : null]);
    }
}
