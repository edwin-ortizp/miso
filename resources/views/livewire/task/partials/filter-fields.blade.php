{{-- Campos de filtro de tareas (FiltersTasks): popover de Lista y barra de las vistas de tablero. --}}
<div class="ltm-section">
    <x-ui.multi-select name="dateFilter" label="Cuándo" :options="\App\Livewire\Task\TaskList::FILTER_DATES" all-label="Cualquier fecha" icon="bi-calendar-event" />
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
