{{-- Campos de filtro de tareas (FiltersTasks): popover de Lista y barra de las vistas de tablero. --}}
<div class="ltm-section">
    <span class="ltm-section__label">Cuándo</span>
    <div class="ltm-chips">
        @foreach (\App\Livewire\Task\TaskList::FILTER_DATES as $value => $label)
            <x-ui.chip variant="filter" :selected="$dateFilter === $value"
                       wire:click="$set('dateFilter', '{{ $dateFilter === $value ? '' : $value }}')">{{ $label }}</x-ui.chip>
        @endforeach
    </div>
</div>
<div class="ltm-section">
    <x-ui.multi-select name="categoryFilter" label="Categoría" :options="['__none__' => 'Sin categoría'] + $categories" all-label="Todas las categorías" icon="bi-tag" />
</div>
<div class="ltm-section">
    <x-ui.multi-select name="filter" label="Estado" :options="$this->taskStatusOptions()" all-label="Todos los estados" icon="bi-circle" />
</div>
<div class="ltm-section">
    <x-ui.select name="priorityFilter" label="Prioridad" wire:model.live="priorityFilter" :options="\App\Livewire\Task\TaskList::FILTER_PRIORITIES" placeholder="Todas las prioridades" />
</div>
<div class="ltm-section">
    <x-ui.select name="sizeFilter" label="Tamaño" wire:model.live="sizeFilter" :options="\App\Livewire\Task\TaskList::FILTER_SIZES" placeholder="Todos los tamaños" />
</div>
