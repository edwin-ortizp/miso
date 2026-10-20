<?php

namespace App\Livewire\Relationship;

use App\Models\Relationship;
use App\Models\RelationshipEvent;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use App\Livewire\Concerns\WithManagementCard;

#[Layout('layouts.app')]
#[Title('Acontecimientos')]
class RelationshipEvents extends Component
{
    use WithManagementCard;

    #[Url(as: 'q', history: true, keep: true)]
    public string $search = '';

    #[Url(as: 'relation', history: true, keep: true)]
    public string $relationFilter = '';

    #[Url(as: 'category', history: true, keep: true)]
    public string $categoryFilter = '';

    #[Url(as: 'period', history: true, keep: true)]
    public string $periodFilter = 'upcoming';

    #[Url(as: 'archived', history: true, keep: true)]
    public bool $showArchived = false;

    /**
     * Deliberately not kept across the module: including sensitive events is a decision
     * for the current query, never a stored preference.
     */
    #[Url(as: 'sensitive')]
    public bool $includeSensitive = false;

    public array $periods = [
        'upcoming' => 'Próximos',
        'past' => 'Pasados',
        'all' => 'Todos',
    ];

    public function mount(): void
    {
        $this->normalizeFilters();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'relationFilter', 'categoryFilter', 'periodFilter', 'showArchived', 'includeSensitive'], true)) {
            $this->normalizeFilters();
            $this->resetPage();
        }
    }

    public function removeFilter(string $key, ?string $value = null): void
    {
        match ($key) {
            'periodFilter' => $this->periodFilter = 'all',
            'showArchived', 'includeSensitive', 'relationFilter', 'categoryFilter' => $this->reset($key),
            default => null,
        };
        $this->resetPage();
    }

    protected function filterProperties(): array
    {
        return ['periodFilter', 'showArchived', 'includeSensitive', 'relationFilter', 'categoryFilter'];
    }

    public function render()
    {
        $relationships = Relationship::orderBy('full_name')->get(['id', 'full_name', 'nickname']);

        $events = $this->filteredQuery()
            ->with('relationship:id,full_name,nickname')
            ->chronological($this->periodFilter === 'past' ? 'desc' : 'asc')
            ->paginate($this->perPage());

        return view('livewire.relationship.relationship-events', [
            'relationships' => $relationships,
            'events' => $events,
            'upcomingCount' => $this->baseQuery()->upcoming()->count(),
            'sensitiveHidden' => $this->includeSensitive
                ? 0
                : $this->baseQuery(includeSensitive: true)->where('is_sensitive', true)->count(),
        ]);
    }

    private function baseQuery(?bool $includeSensitive = null): Builder
    {
        return RelationshipEvent::query()
            ->visibleGlobally($includeSensitive ?? $this->includeSensitive)
            ->when($this->showArchived, fn (Builder $query) => $query->archived(), fn (Builder $query) => $query->active());
    }

    private function filteredQuery(): Builder
    {
        $query = $this->baseQuery();

        if ($this->relationFilter !== '') {
            $query->where('relationship_id', $this->relationFilter);
        }

        if ($this->categoryFilter !== '') {
            $query->whereIn('category', \App\Support\CategoryCatalog::expand('relationship-events', [$this->categoryFilter]));
        }

        if ($this->periodFilter === 'upcoming') {
            $query->upcoming();
        } elseif ($this->periodFilter === 'past') {
            $query->past();
        }

        if (trim($this->search) !== '') {
            $term = trim($this->search);

            $query->where(function (Builder $inner) use ($term): void {
                $inner->where('title', 'like', "%{$term}%")
                    ->orWhere('notes', 'like', "%{$term}%")
                    ->orWhereHas('relationship', fn (Builder $relation) => $relation->where('full_name', 'like', "%{$term}%"));
            });
        }

        return $query;
    }

    private function normalizeFilters(): void
    {
        if (! array_key_exists($this->periodFilter, $this->periods)) {
            $this->periodFilter = 'upcoming';
        }

        if ($this->categoryFilter !== '' && ! array_key_exists($this->categoryFilter, \App\Support\CategoryCatalog::options('relationship-events'))) {
            $this->categoryFilter = '';
        }

        if ($this->relationFilter !== '' && ! Relationship::whereKey($this->relationFilter)->exists()) {
            $this->relationFilter = '';
        }
    }
}
