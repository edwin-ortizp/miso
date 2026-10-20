<?php

namespace App\Livewire\Task\Concerns;

use App\Models\TaskCategory;
use App\Models\TaskStatus;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

/**
 * Filtros compartidos de las vistas de tareas (Lista, Gantt, Planificación, Kanban y Flujo):
 * cuándo, estado, categoría, prioridad, tamaño y búsqueda. Viven en la URL con los mismos alias
 * para que la cabecera del módulo los conserve al cambiar de pestaña.
 * Requiere LoadsTaskCategories para validar y rotular las categorías.
 */
trait FiltersTasks
{
    public const FILTER_DATES = ['hoy' => 'Hoy', 'vencidas' => 'Vencidas', 'proximas' => 'Próximas', 'sin-fecha' => 'Sin fecha'];

    public const FILTER_PRIORITIES = [
        'urgent-important' => '🔴 Urgente e Importante',
        'not-urgent-important' => '🟡 No Urgente pero Importante',
        'urgent-not-important' => '🟠 Urgente No Importante',
        'not-urgent-not-important' => '⚪ No Urgente No Importante',
    ];

    public const FILTER_SIZES = ['XS' => 'XS (< 30 min)', 'S' => 'S (30 min - 1h)', 'M' => 'M (1-2h)', 'L' => 'L (2-4h)', 'XL' => 'XL (> 4h)'];

    /** Estado en la URL: 'pending', 'completed', 'all' o una lista de ids; null = el de la vista. */
    #[Url(as: 'status', history: true, keep: true)]
    public $statusQuery = null;

    public $filter = [];

    #[Url(as: 'category', history: true, keep: true)]
    public $categoryFilter = [];

    #[Url(as: 'priority', history: true, keep: true)]
    public string $priorityFilter = '';

    #[Url(as: 'date', history: true, keep: true)]
    public string $dateFilter = '';

    #[Url(as: 'size', history: true, keep: true)]
    public string $sizeFilter = '';

    #[Url(as: 'q', history: true, keep: true)]
    public string $search = '';

    public function mountFiltersTasks(): void
    {
        $this->filter = $this->statusQuery ?? $this->defaultStatusFilter();
        $this->normalizeFilters();
    }

    /** Estado con el que abre la vista cuando la URL no trae uno. */
    protected function defaultStatusFilter(): string
    {
        return 'all';
    }

    public function updatedFiltersTasks(string $property): void
    {
        if (! in_array($property, ['statusQuery', 'filter', 'categoryFilter', 'priorityFilter', 'dateFilter', 'sizeFilter', 'search'], true)
            && ! str_starts_with($property, 'filter.') && ! str_starts_with($property, 'categoryFilter.')) {
            return;
        }

        if ($property === 'statusQuery') {
            $this->filter = $this->statusQuery;
        }
        $this->normalizeFilters();
        $this->filtersChanged();
    }

    public function clearFilters(): void
    {
        $this->filter = $this->defaultStatusFilter();
        $this->reset('dateFilter', 'categoryFilter', 'priorityFilter', 'sizeFilter');
        $this->normalizeFilters();
        $this->filtersChanged();
    }

    public function removeFilter(string $key, ?string $value = null): void
    {
        match ($key) {
            'date' => $this->dateFilter = '',
            'priority' => $this->priorityFilter = '',
            'size' => $this->sizeFilter = '',
            'category' => $this->categoryFilter = array_values(array_diff($this->categoryFilter, [$value])),
            'status' => $this->filter = $value === null ? 'all' : array_values(array_diff($this->filter, [$value])),
            default => null,
        };
        $this->normalizeFilters();
        $this->filtersChanged();
    }

    /** Reacción de la vista a un cambio de filtros (paginación, redibujar el Gantt…). */
    protected function filtersChanged(): void
    {
        if (method_exists($this, 'resetPage')) {
            $this->resetPage();
        }
    }

    /** Número de filtros activos (badge del botón «Filtros»); la búsqueda va aparte. */
    public function taskFilterCount(): int
    {
        return collect([$this->dateFilter, $this->categoryFilter, $this->priorityFilter, $this->sizeFilter, $this->filter])->filter()->count();
    }

    /** @return array<string, string> id => nombre de los estados del usuario. */
    public function taskStatusOptions(): array
    {
        return $this->taskStatuses()->pluck('name', 'id')->all();
    }

    /** Chips de la franja «Filtros aplicados»; la búsqueda la agrega la franja. */
    public function taskFilterChips(): array
    {
        $filters = [];
        if ($this->dateFilter !== '') {
            $filters[] = ['key' => 'date', 'value' => null, 'label' => 'Cuándo: '.self::FILTER_DATES[$this->dateFilter], 'icon' => 'bi-calendar-event'];
        }

        $statuses = $this->taskStatuses();
        $pending = $statuses->where('type', '!=', TaskStatus::DONE)->pluck('id')->sort()->values()->all();
        $selected = collect($this->filter)->sort()->values()->all();
        if ($selected !== [] && $selected === $pending) {
            $filters[] = ['key' => 'status', 'value' => null, 'label' => 'Estado: Pendientes', 'icon' => 'bi-circle'];
        } else {
            foreach ($this->filter as $id) {
                $filters[] = ['key' => 'status', 'value' => $id, 'label' => 'Estado: '.($statuses->firstWhere('id', $id)?->name ?? $id), 'icon' => 'bi-circle'];
            }
        }

        foreach ($this->categoryFilter as $category) {
            $label = $category === '__none__' ? 'Sin categoría' : ($this->categories[$category] ?? $category);
            $filters[] = ['key' => 'category', 'value' => $category, 'label' => 'Categoría: '.$label, 'icon' => 'bi-tag'];
        }
        if ($this->priorityFilter !== '') {
            $filters[] = ['key' => 'priority', 'value' => null, 'label' => 'Prioridad: '.self::FILTER_PRIORITIES[$this->priorityFilter], 'icon' => 'bi-flag'];
        }
        if ($this->sizeFilter !== '') {
            $filters[] = ['key' => 'size', 'value' => null, 'label' => 'Tamaño: '.$this->sizeFilter, 'icon' => 'bi-rulers'];
        }

        return $filters;
    }

    /** Aplica los filtros activos a una consulta de tareas. */
    protected function applyTaskFilters(Builder $query): Builder
    {
        if ($this->filter) {
            $query->whereIn('status_id', $this->filter);
        }

        if ($this->categoryFilter) {
            $values = TaskCategory::expandSelection($this->categoryFilter, auth()->id(), 'key');
            $query->where(function ($q) use ($values) {
                $q->whereIn('category', array_values(array_diff($values, ['__none__'])));
                if (in_array('__none__', $values, true)) {
                    $q->orWhereNull('category');
                }
            });
        }

        if ($this->priorityFilter) {
            $query->where('priority', $this->priorityFilter);
        }

        if ($this->sizeFilter) {
            $query->where('size', $this->sizeFilter);
        }

        match ($this->dateFilter) {
            'sin-fecha' => $query->whereNull('start_date')->whereNull('end_date'),
            'vencidas' => $query->where('completed', false)->where(fn ($q) => $q
                ->where('end_date', '<', now())
                ->orWhere(fn ($q2) => $q2->whereNull('end_date')->where('start_date', '<', now()))
            ),
            'hoy' => $query->where(fn ($q) => $q
                ->whereDate('start_date', today())
                ->orWhereDate('end_date', today())
            ),
            'proximas' => $query->where(fn ($q) => $q
                ->where('start_date', '>', now())
                ->orWhere(fn ($q2) => $q2->whereNull('start_date')->where('end_date', '>', now()))
            ),
            default => null,
        };

        if (trim($this->search) !== '') {
            $term = '%'.trim($this->search).'%';
            $query->where(fn ($q) => $q
                ->where('title', 'like', $term)
                ->orWhere('description', 'like', $term)
            );
        }

        return $query;
    }

    protected function normalizeFilters(): void
    {
        $statuses = $this->taskStatuses();
        $values = match (true) {
            $this->filter === 'pending' => $statuses->where('type', '!=', TaskStatus::DONE)->pluck('id')->all(),
            $this->filter === 'completed' => $statuses->where('type', TaskStatus::DONE)->pluck('id')->all(),
            $this->filter === 'all', $this->filter === null => [],
            is_array($this->filter) => $this->filter,
            default => [$this->filter],
        };
        $this->filter = array_values(array_unique(array_filter($values,
            fn ($value) => is_string($value) && $statuses->contains('id', $value))));
        // Livewire omite los arreglos vacíos en la URL; un valor explícito conserva «todos» al recargar.
        $this->statusQuery = $this->filter ?: 'all';

        $values = is_array($this->categoryFilter) ? $this->categoryFilter : [$this->categoryFilter];
        $this->categoryFilter = array_values(array_unique(array_filter($values,
            fn ($value) => is_string($value) && ($value === '__none__' || array_key_exists($value, $this->categories)))));

        if ($this->priorityFilter !== '' && ! array_key_exists($this->priorityFilter, self::FILTER_PRIORITIES)) {
            $this->priorityFilter = '';
        }

        if ($this->dateFilter !== '' && ! array_key_exists($this->dateFilter, self::FILTER_DATES)) {
            $this->dateFilter = '';
        }

        if ($this->sizeFilter !== '' && ! array_key_exists($this->sizeFilter, self::FILTER_SIZES)) {
            $this->sizeFilter = '';
        }
    }

    private function taskStatuses()
    {
        return TaskStatus::query()->where('user_id', auth()->id())->ordered()->get();
    }
}
