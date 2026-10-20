<?php

namespace App\Livewire\Relationship;

use App\Livewire\Plan\Concerns\ManagesPlanDialogs;
use App\Models\Plan;
use App\Models\PlanLink;
use App\Models\PlanVisit;
use App\Models\Relationship;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use App\Livewire\Concerns\WithManagementCard;

#[Layout('layouts.app')]
#[Title('Planes de la relación')]
class RelationshipPlans extends Component
{
    use ManagesPlanDialogs;
    use WithManagementCard;

    public const STATUS_FILTERS = [
        '' => ['label' => 'Todos', 'icon' => 'bi-grid'],
        'pending' => ['label' => 'Pendientes', 'icon' => 'bi-bookmark'],
        'scheduled' => ['label' => 'Programados', 'icon' => 'bi-calendar-event'],
        'done' => ['label' => 'Realizados', 'icon' => 'bi-check2'],
    ];

    public string $relationshipId;

    #[Url(as: 'status', history: true)]
    public string $status = '';

    public ?string $surpriseMessage = null;

    public function mount(string $relationship): void
    {
        $this->relationshipId = Relationship::query()->findOrFail($relationship)->id;
        $this->status = array_key_exists($this->status, self::STATUS_FILTERS) ? $this->status : '';
    }

    protected function defaultPlanPeople(): array
    {
        return [$this->relationshipId];
    }

    public function setStatus(string $status): void
    {
        $this->resetPage();
        $this->status = array_key_exists($status, self::STATUS_FILTERS) ? $status : '';
        $this->surpriseMessage = null;
    }

    public function surprise(): void
    {
        $this->surpriseFrom($this->baseQuery($this->relationship()));
    }

    protected function filterProperties(): array
    {
        return ['status'];
    }

    public function render()
    {
        $relationship = $this->relationship();
        $base = $this->baseQuery($relationship);
        $counts = (clone $base)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $plans = $this->sortPlans(
            (clone $base)
                ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
                ->withVisitStats($relationship->id)
                ->with(['circles', 'images'])
                ->get(),
            'recent',
        );
        $plans = $this->paginateCollection($plans);

        return view('livewire.relationship.relationship-plans', [
            'relationship' => $relationship,
            'firstName' => $relationship->nickname ?: Str::before($relationship->full_name.' ', ' '),
            'plans' => $plans,
            'counts' => $counts,
            'total' => $counts->sum(),
            'statusFilters' => self::STATUS_FILTERS,
            'upcoming' => (clone $base)
                ->whereIn('status', ['pending', 'scheduled'])
                ->whereDate('scheduled_on', '>=', today())
                ->orderBy('scheduled_on')
                ->limit(3)
                ->get(),
            'recentVisits' => PlanVisit::query()
                ->whereHas('relationships', fn (Builder $people) => $people->whereKey($relationship->id))
                ->with('plan')
                ->orderByDesc('visited_on')
                ->limit(3)
                ->get(),
            'recentLinks' => PlanLink::query()
                ->whereHas('plan', fn (Builder $query) => $query->forRelationship($relationship))
                ->with('plan')
                ->latest()
                ->limit(3)
                ->get(),
        ] + $this->planDialogOptions());
    }

    private function relationship(): Relationship
    {
        return Relationship::query()->findOrFail($this->relationshipId);
    }

    private function baseQuery(Relationship $relationship): Builder
    {
        return Plan::query()->forRelationship($relationship)->where('status', '!=', 'archived');
    }
}
