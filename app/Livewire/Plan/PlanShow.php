<?php

namespace App\Livewire\Plan;

use App\Livewire\Plan\Concerns\ManagesPlanDialogs;
use App\Models\Plan;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Detalle del plan')]
class PlanShow extends Component
{
    use ManagesPlanDialogs;

    public string $planId;

    public ?string $surpriseMessage = null;

    public function mount(string $plan): void
    {
        $this->planId = Plan::query()->findOrFail($plan)->id;
    }

    public function deletePlan(string $id): void
    {
        Plan::query()->findOrFail($id)->delete();

        $this->redirectRoute('plans', navigate: true);
    }

    public function render()
    {
        $plan = Plan::query()
            ->with(['circles', 'relationships', 'tags', 'links', 'images', 'planCategory.parent'])
            ->withVisitStats()
            ->findOrFail($this->planId);

        $visits = $plan->visits()
            ->with('relationships')
            ->orderByDesc('visited_on')
            ->orderByDesc('created_at')
            ->get();

        // Everyone associated with the plan or who went at least once, with their own count.
        $people = $visits
            ->flatMap(fn ($visit) => $visit->relationships->map(fn ($person) => ['person' => $person, 'date' => $visit->visited_on]))
            ->groupBy(fn (array $row) => $row['person']->id)
            ->map(fn ($rows) => ['person' => $rows->first()['person'], 'count' => $rows->count(), 'last' => $rows->max('date')]);

        foreach ($plan->relationships as $person) {
            $people->put($person->id, $people->get($person->id, ['person' => $person, 'count' => 0, 'last' => null]));
        }

        return view('livewire.plan.plan-show', [
            'plan' => $plan,
            'visits' => $visits,
            'people' => $people->sortByDesc('count')->values(),
        ] + $this->planDialogOptions());
    }
}
