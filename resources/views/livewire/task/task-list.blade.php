<div data-module="tasks" class="lt-page" x-data="{ showDialog: $wire.entangle('showForm'), showRecurringDialog: $wire.entangle('showRecurringCompletion') }">
    <x-page-header subtitle="Decide, ordena y completa el trabajo con claridad." :tabs="config('modules.tasks.tabs')" :preserve="config('modules.tasks.preserve')">
    </x-page-header>

    <div class="lt-cols task-list-layout">
    <div class="lt-stack">
    @php
        $activeFilterCount = collect([$dateFilter, $categoryFilter, $priorityFilter, $sizeFilter, $filter])->filter()->count();
    @endphp
    <x-ui.management-card id="task-list" title="Tareas" icon="bi-list-task" :count="'('.$tasks->total().')'"
                          search="search" search-placeholder="Buscar tareas" :active-filters="$activeFilterCount"
                          sort-model="sort" :sort-options="$sorts" :sort-value="$sort"
                          :paginator="$tasks" noun="tareas" flush>
        <x-slot:filters>
            @include('livewire.task.partials.filter-fields')
        </x-slot:filters>
        @if (count($activeFilters) > 0)
            <x-slot:strip>
                <x-ui.applied-filters :filters="$activeFilters" />
            </x-slot:strip>
        @endif
        @if ($activeFilterCount)
            <x-slot:filterActions>
                <x-ui.action variant="outlined" icon="bi-eraser" wire:click="clearFilters" x-on:click="filtersOpen = false">Limpiar</x-ui.action>
                <x-ui.action variant="filled" icon="bi-funnel-fill" x-on:click="filtersOpen = false">Filtrar</x-ui.action>
            </x-slot:filterActions>
        @endif
        <x-slot:menu>
            <x-ui.menu-item icon="bi-calendar-week" :href="route('tasks.planning')">Planificación</x-ui.menu-item>
            <x-ui.menu-item icon="bi-trophy" :href="route('tasks.progress')">Progreso</x-ui.menu-item>
        </x-slot:menu>
        {{-- Mientras se resuelve un filtro, una busqueda o un cambio de pagina
             se muestra la silueta de la lista en lugar de dejar la pantalla quieta. --}}
        <x-ui.skeleton variant="list" :lines="6" label="Cargando tareas"
                       wire:loading.delay
                       wire:target="filter,categoryFilter,priorityFilter,dateFilter,sizeFilter,search,gotoPage,previousPage,nextPage" />

        <div wire:loading.delay.remove wire:target="filter,categoryFilter,priorityFilter,dateFilter,sizeFilter,search,gotoPage,previousPage,nextPage">
        @forelse ($tasks as $task)
                <div class="md-list-item task-row {{ $task->completed ? 'md-list-item--completed' : '' }}" wire:key="task-row-{{ $task->id }}">
                <button x-on:click.stop="$dispatch('task-editor', {action: 'openForm', id: '{{ $task->id }}'})" class="md-list-item-content md-task-open-button" aria-label="Abrir tarea: {{ $task->title }}">
                    <div class="d-flex align-items-center gap-2">
                        <span class="md-list-item-headline {{ $task->completed ? '' : 'fw-medium' }}">
                            <x-ui.task-code :code="$task->code" /> {{ $task->title }}
                        </span>
                        @if ($task->is_private)
                            <i class="bi bi-lock-fill" style="color: var(--md-sys-color-on-surface-variant); font-size: 0.75rem;"></i>
                        @endif
                    </div>
                    @if ($task->description)
                        <div class="md-list-item-supporting text-truncate">{{ $task->description }}</div>
                    @endif
                    <div class="d-flex flex-wrap gap-1 mt-1">
                        @if ($task->priority)
                            @php
                                $priorityChipClass = match($task->priority) {
                                    'urgent-important' => 'md-chip-tonal--error',
                                    'not-urgent-important' => 'md-chip-tonal--warning',
                                    'urgent-not-important' => 'md-chip-tonal--info',
                                    default => 'md-chip-tonal',
                                };
                            @endphp
                            <span class="md-chip-tonal {{ $priorityChipClass }}">{{ $priorities[$task->priority] ?? $task->priority }}</span>
                        @endif
                        @if ($task->category)
                            <span class="md-chip-tonal md-chip-tonal--primary">{{ $categories[$task->category] ?? $task->category }}</span>
                        @endif
                        @if ($task->size)
                            <span class="md-chip-tonal">{{ $task->size }}</span>
                        @endif
                        @if ($task->subtask_progress)
                            <span class="md-chip-tonal md-chip-tonal--info">
                                <i class="bi bi-check2-square" style="font-size: 0.625rem;"></i> {{ $task->subtask_progress['completed'] }}/{{ $task->subtask_progress['total'] }} subtareas
                            </span>
                        @endif
                        @if ($task->is_recurrent)
                            @php
                                $recurrence = $task->recurrence ?? [];
                                $recurrenceLabel = match ($recurrence['pattern'] ?? 'custom') {
                                    'daily' => 'Diaria',
                                    'weekly' => 'Semanal',
                                    'monthly' => 'Mensual',
                                    default => 'Cada '.max(1, (int) ($recurrence['customDays'] ?? 1)).' días',
                                };
                            @endphp
                            <span class="md-chip-tonal"><i class="bi bi-arrow-repeat" style="font-size: 0.625rem;"></i> {{ $recurrenceLabel }}</span>
                        @endif
                        @if (!$task->start_date && !$task->end_date)
                            <span class="md-chip-tonal">Sin fecha</span>
                        @else
                            @if (!$task->completed && ($task->end_date ?? $task->start_date)->isPast())
                                <span class="md-chip-tonal md-chip-tonal--error"><i class="bi bi-exclamation-circle" style="font-size: 0.625rem;"></i> Vencida</span>
                            @endif
                            <span class="md-chip-tonal">
                                <i class="bi bi-calendar" style="font-size: 0.625rem;"></i> {{ ($task->start_date ?? $task->end_date)->format('d M, H:i') }}@if($task->end_date && $task->start_date) – {{ $task->end_date->format('H:i') }}@endif
                            </span>
                        @endif
                        @if ($task->estimated_time)<span class="md-chip-tonal"><i class="bi bi-clock" style="font-size: 0.625rem;"></i> {{ $task->estimated_time_label }}</span>@endif
                    </div>
                </button>
                <div class="task-row__status">
                    @if ($task->status)
                        <span class="md-chip-tonal {{ $task->completed ? 'md-chip-tonal--success' : '' }}" title="Estado"><i class="bi {{ $task->status->icon }}" aria-hidden="true"></i> {{ $task->status->name }}</span>
                    @endif
                </div>
                <div class="md-list-item-trailing">
                    <x-ui.row-actions :label="'Más acciones de '.$task->title">
                        <x-slot:primary wire:click.stop="advanceStatus('{{ $task->id }}')">{{ $this->advanceLabel($task) }}</x-slot:primary>
                        <x-ui.menu-item icon="bi-pencil" x-on:click="$dispatch('task-editor', {action: 'openForm', id: '{{ $task->id }}'})">Editar</x-ui.menu-item>
                        <x-ui.menu-divider />
                        <x-ui.menu-item icon="bi-trash" tone="danger" wire:click="delete('{{ $task->id }}')" wire:confirm="La tarea «{{ $task->title }}» se elimina de forma permanente.">Eliminar</x-ui.menu-item>
                    </x-ui.row-actions>
                </div>
                </div>
        @empty
            <div class="text-center py-5" style="color: var(--md-sys-color-on-surface-variant);">
                <i class="bi bi-list-task" style="font-size: 3rem; opacity: 0.4;"></i>
                <p class="md-body-large mt-3 mb-0">No hay tareas{{ $activeFilterCount || $search !== '' ? ' con los filtros actuales' : '' }}</p>
            </div>
        @endforelse
        </div>
    </x-ui.management-card>
    </div>

    <div class="lt-stack">
        <x-panel title="Hoy" icon="bi-lightning-charge">
            <div style="display: grid; gap: 14px;">
                <div>
                    <p class="lt-figure" style="margin: 0 0 8px;"><strong>{{ $completedToday }}</strong><span>de {{ $plannedToday }} planificadas hoy</span></p>
                    <x-ui.progress :value="$plannedToday ? ($completedToday / $plannedToday) * 100 : 0" tone="success" label="Progreso de hoy" />
                </div>
                <dl class="lt-facts">
                    <div><dt>Pendientes</dt><dd>{{ $pendingCount }}</dd></div>
                    @if ($overdueCount > 0)
                        <div style="color: var(--md-sys-color-error);"><dt>Vencidas</dt><dd>{{ $overdueCount }}</dd></div>
                    @endif
                </dl>
            </div>
        </x-panel>
        @if (!empty($categoryBreakdown))
            <x-panel title="Completadas hoy" icon="bi-bar-chart">
                <dl class="lt-facts">
                    @foreach ($categoryBreakdown as $cat => $count)
                        <div><dt>{{ $categories[$cat] ?? $cat }}</dt><dd>{{ $count }}</dd></div>
                    @endforeach
                </dl>
            </x-panel>
        @endif
        <x-panel title="Vistas relacionadas" icon="bi-signpost-split">
            <div class="md-context-links">
                <a href="{{ route('tasks.planning') }}"><i class="bi bi-calendar-week"></i> Planificación</a>
                <a href="{{ route('tasks.progress') }}"><i class="bi bi-trophy"></i> Progreso</a>
            </div>
        </x-panel>
    </div>
    </div>

    <div class="lt-fab-zone">
        <x-module-actions fab-always :split="true"
            :primary="['label' => 'Nueva tarea', 'icon' => 'bi-plus-lg', 'event' => 'task-editor', 'detail' => ['action' => 'openForm']]"
            :secondary="[['label' => 'Crear múltiples tareas', 'icon' => 'bi-list-task', 'event' => 'task-bulk-editor', 'detail' => ['action' => 'openForm'], 'create' => true]]" />
    </div>

    <livewire:task.task-editor :edit-task="$editTask" />
    <livewire:task.task-bulk-editor />

    @include('livewire.task.partials.recurring-completion-dialog')
</div>
