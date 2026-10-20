{{--
    Barra de filtros de las vistas de tablero de tareas (Gantt, Planificación, Kanban, Flujo):
    misma card de gestión que Lista, sin cuerpo. Requiere FiltersTasks + LoadsTaskCategories.
--}}
@php
    $taskFilterChips = $this->taskFilterChips();
    $taskFilterCount = $this->taskFilterCount();
@endphp
<x-ui.management-card id="task-filters" title="{{ $title ?? 'Tareas' }}" icon="{{ $icon ?? 'bi-funnel' }}" class="task-filter-bar"
                      search="search" search-placeholder="Buscar tareas" :active-filters="$taskFilterCount" :refreshable="false">
    <x-slot:filters>
        @include('livewire.task.partials.filter-fields')
    </x-slot:filters>
    @if ($taskFilterCount)
        <x-slot:filterActions>
            <x-ui.action variant="outlined" icon="bi-eraser" wire:click="clearFilters" x-on:click="filtersOpen = false">Limpiar</x-ui.action>
            <x-ui.action variant="filled" icon="bi-funnel-fill" x-on:click="filtersOpen = false">Filtrar</x-ui.action>
        </x-slot:filterActions>
    @endif
    @if ($taskFilterChips !== [])
        <x-slot:strip>
            <x-ui.applied-filters :filters="$taskFilterChips" />
        </x-slot:strip>
    @endif
</x-ui.management-card>
