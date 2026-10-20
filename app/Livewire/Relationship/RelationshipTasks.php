<?php

namespace App\Livewire\Relationship;

use App\Livewire\Concerns\WithManagementCard;
use App\Livewire\Relationship\Concerns\ManagesRelationshipTasks;
use App\Livewire\Relationship\Concerns\ResolvesRelationship;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Tareas relacionadas con una persona como card de gestión. Son tareas ordinarias del módulo Tareas.
 */
#[Layout('layouts.app')]
#[Title('Tareas de la relación')]
class RelationshipTasks extends Component
{
    use ManagesRelationshipTasks;
    use ResolvesRelationship;
    use WithManagementCard;

    public const STATUS_FILTERS = [
        'pending' => ['label' => 'Pendientes', 'icon' => 'bi-circle'],
        'completed' => ['label' => 'Completadas', 'icon' => 'bi-check2-circle'],
        'all' => ['label' => 'Todas', 'icon' => 'bi-grid'],
    ];

    #[Url(as: 'q', history: true, except: '')]
    public string $search = '';

    #[Url(as: 'status', history: true, except: 'pending')]
    public string $status = 'pending';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function setStatus(string $status): void
    {
        $this->status = array_key_exists($status, self::STATUS_FILTERS) ? $status : 'pending';
        $this->resetPage();
    }

    public function removeFilter(string $key, ?string $value = null): void
    {
        if ($key === 'status') {
            $this->status = 'all';
        }
        $this->resetPage();
    }

    protected function filterProperties(): array
    {
        return ['status'];
    }

    public function render()
    {
        $this->status = array_key_exists($this->status, self::STATUS_FILTERS) ? $this->status : 'pending';

        $relationship = $this->relationship();
        $pending = $relationship->tasks()->where('tasks.completed', false)->count();
        $completed = $relationship->tasks()->where('tasks.completed', true)->count();
        $search = trim($this->search);

        $tasks = $relationship->tasks()
            ->when($this->status === 'pending', fn (Builder $query) => $query->where('tasks.completed', false))
            ->when($this->status === 'completed', fn (Builder $query) => $query->where('tasks.completed', true))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $text) => $text
                ->where('tasks.title', 'like', '%'.$search.'%')
                ->orWhere('tasks.description', 'like', '%'.$search.'%')))
            ->orderBy('tasks.completed')
            ->orderByRaw('CASE WHEN tasks.end_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('tasks.end_date')
            ->orderByDesc('tasks.created_at')
            ->paginate($this->perPage());

        return view('livewire.relationship.relationship-tasks', [
            'relationship' => $relationship,
            'tasks' => $tasks,
            'counts' => ['pending' => $pending, 'completed' => $completed, 'all' => $pending + $completed],
            'statusFilters' => self::STATUS_FILTERS,
        ]);
    }
}
