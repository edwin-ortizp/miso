<?php

namespace App\Livewire\Home\Panel;

use App\Livewire\Plan\Concerns\ManagesPlanDialogs;
use App\Models\Plan;
use Livewire\Attributes\On;
use Livewire\Component;

class PlanEditor extends Component
{
    use ManagesPlanDialogs { savePlan as private persistPlan;
        saveVisit as private persistVisit; }

    public function boot(): void
    {
        abort_unless(auth()->check(), 403);
    }

    #[On('panel-plan')]
    public function openPanelPlan(?string $id = null, bool $visit = false): void
    {
        if ($visit && $id) {
            $this->openVisitForm($id);
            $this->visitPeople = Plan::findOrFail($id)->relationships()->pluck('relationships.id')->all();
        } else {
            $this->openPlanForm($id);
        }
    }

    public function savePlan(): void
    {
        $this->persistPlan();
        if (! $this->showPlanForm) {
            $this->dispatch('panel-changed');
        }
    }

    public function saveVisit(): void
    {
        $this->persistVisit();
        if (! $this->showVisitForm) {
            $this->dispatch('panel-changed');
        }
    }

    public function render()
    {
        return view('livewire.home.panel.plan-editor', $this->planDialogOptions());
    }
}
