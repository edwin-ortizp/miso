<div data-module="tasks" class="lt-page" x-data="{ showDialog: $wire.entangle('showForm'), showRecurringDialog: $wire.entangle('showRecurringCompletion') }">
    <div data-region="navigation">
        <x-module-tabs :tabs="config('modules.tasks.tabs')" :preserve="config('modules.tasks.preserve')" label="Vistas del módulo" />
    </div>

    <div class="lt-cols task-list-layout">
    <div class="lt-stack">
    <livewire:game.guardian-card module="tasks" variant="top" />
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
                            <x-ui.catalog-chip :color="$this->categoryColor($task->category)" tone="accent" :icon="$this->categoryIcon($task->category)">{{ $categories[$task->category] ?? $task->category }}</x-ui.catalog-chip>
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
                            @if ($task->isOverdue())
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
                        <x-ui.catalog-chip :color="$task->status->color" tone="accent" :icon="$task->status->icon" title="Estado">{{ $task->status->name }}</x-ui.catalog-chip>
                    @endif
                </div>
                <div class="md-list-item-trailing">
                    <x-ui.row-actions :label="'Más acciones de '.$task->title">
                        <x-slot:primary wire:click.stop="advanceStatus('{{ $task->id }}')">{{ $this->advanceLabel($task) }}</x-slot:primary>
                        @unless ($task->completed)
                            <x-ui.menu-item icon="bi-check2-circle" tone="success" wire:click="completeTask('{{ $task->id }}')" wire:loading.attr="disabled">Completar</x-ui.menu-item>
                            <x-ui.menu-item icon="bi-calendar-event" wire:click="moveToDay('{{ $task->id }}', 0)" wire:loading.attr="disabled">Mover para hoy</x-ui.menu-item>
                            <x-ui.menu-item icon="bi-calendar-plus" wire:click="moveToDay('{{ $task->id }}', 1)" wire:loading.attr="disabled">Mover para mañana</x-ui.menu-item>
                            <x-ui.menu-divider />
                            @if ($focusAvailable)
                                @php $focusMinutes = $this->focusSuggestionMinutes($task); @endphp
                                <x-ui.menu-item icon="bi-stopwatch"
                                    @click="$dispatch('task-focus-timer', { taskId: {{ \Illuminate\Support\Js::from($task->id) }}, durationMinutes: {{ \Illuminate\Support\Js::from($focusMinutes) }}, start: true })">Iniciar enfoque</x-ui.menu-item>
                                <x-ui.menu-item icon="bi-clock-history"
                                    @click="$dispatch('task-focus-manual', { taskId: {{ \Illuminate\Support\Js::from($task->id) }}, durationMinutes: {{ \Illuminate\Support\Js::from($focusMinutes) }} })">Registrar tiempo trabajado</x-ui.menu-item>
                                <x-ui.menu-divider />
                            @endif
                        @endunless
                        <x-ui.menu-item icon="bi-pencil" x-on:click="$dispatch('task-editor', {action: 'openForm', id: '{{ $task->id }}', mode: 'edit'})">Editar</x-ui.menu-item>
                        <x-ui.menu-divider />
                        <x-ui.menu-item icon="bi-trash" tone="danger" wire:click="delete('{{ $task->id }}')" wire:confirm="La tarea «{{ $task->title }}» se elimina de forma permanente.">Eliminar</x-ui.menu-item>
                    </x-ui.row-actions>
                </div>
                </div>
        @empty
            <div class="text-center py-5" style="color: var(--md-sys-color-on-surface-variant);">
                <i class="bi bi-list-task" style="font-size: 3rem; opacity: 0.4;"></i>
                <p class="md-body-large mt-3 mb-0">No hay tareas{{ $activeFilterCount || $search !== '' ? ' con los filtros actuales' : '' }}</p>
                @if (! $activeFilterCount && $search === '')
                    <p class="md-body-medium mt-2 mb-0">¿Algo dando vueltas por tu cabeza? Crea una tarea y lo ponemos por escrito.</p>
                @endif
            </div>
        @endforelse
        </div>
    </x-ui.management-card>
    </div>

    <div class="lt-stack">
        <x-panel title="Hoy" icon="bi-lightning-charge">
            <livewire:game.guardian-card module="tasks" variant="rail" />
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
        @php($fmt = fn ($m) => \App\Livewire\Task\TaskList::formatMinutes($m))
        <x-panel title="Hoy por categoría" icon="bi-clock-history">
            @if (empty($todayStats['rows']))
                <p class="lt-cat-empty">Hoy no hay tareas planificadas ni completadas.</p>
            @else
                <p class="lt-figure" style="margin: 0 0 12px;"><strong>{{ $fmt($todayStats['minutes']) }}</strong><span>en {{ $todayStats['count'] }} {{ $todayStats['count'] === 1 ? 'tarea' : 'tareas' }}</span></p>
                @php($maxToday = max(1, max(array_column($todayStats['rows'], 'minutes'))))
                <ul class="lt-cat-list">
                    @foreach ($todayStats['rows'] as $row)
                        <li class="lt-cat-row" style="--cat: {{ $row['color'] }};">
                            <div class="lt-cat-row__head">
                                <span class="lt-cat-dot" aria-hidden="true"></span>
                                <span class="lt-cat-row__name">{{ $row['label'] }}</span>
                                <span class="lt-cat-row__meta" title="Completadas de las del día">{{ $row['done'] }}/{{ $row['count'] }}</span>
                                <span class="lt-cat-row__value">{{ $fmt($row['minutes']) }}</span>
                            </div>
                            <div class="lt-cat-bar" role="img" aria-label="{{ $row['label'] }}: {{ $fmt($row['minutes']) }}, {{ $row['done'] }} de {{ $row['count'] }} completadas">
                                <span style="width: {{ round($row['minutes'] / $maxToday * 100) }}%;"></span>
                            </div>
                        </li>
                    @endforeach
                </ul>
                @if ($todayStats['unestimated'] > 0)
                    <p class="lt-cat-note"><i class="bi bi-info-circle"></i> {{ $todayStats['unestimated'] }} {{ $todayStats['unestimated'] === 1 ? 'tarea sin duración no suma' : 'tareas sin duración no suman' }} horas.</p>
                @endif
            @endif
        </x-panel>

        <x-panel title="Esta semana" icon="bi-calendar-week">
            @if (empty($weekStats['rows']))
                <p class="lt-cat-empty">Esta semana aún no tiene tareas.</p>
            @else
                <div x-data="{ metric: 'count' }" class="lt-week">
                    <div class="lt-week__totals">
                        <div><strong>{{ $weekStats['count'] }}</strong><span>tareas · {{ $weekStats['done'] }} hechas</span></div>
                        <div><strong>{{ $fmt($weekStats['minutes']) }}</strong><span>de trabajo</span></div>
                    </div>
                    <div class="lt-segmented" role="group" aria-label="Ver por">
                        <button type="button" :class="{ 'is-active': metric === 'count' }" :aria-pressed="(metric === 'count').toString()" x-on:click="metric = 'count'">Tareas</button>
                        <button type="button" :class="{ 'is-active': metric === 'minutes' }" :aria-pressed="(metric === 'minutes').toString()" x-on:click="metric = 'minutes'">Horas</button>
                    </div>
                    @foreach (['count', 'minutes'] as $metric)
                        @php($total = max(1, $weekStats[$metric]))
                        @php($max = max(1, max(array_column($weekStats['rows'], $metric))))
                        <div x-show="metric === '{{ $metric }}'" @if ($metric === 'minutes') x-cloak @endif>
                            <div class="lt-stack-bar" aria-hidden="true">
                                @foreach ($weekStats['rows'] as $row)
                                    @if ($row[$metric] > 0)
                                        <span style="flex: {{ $row[$metric] }}; background: {{ $row['color'] }};" title="{{ $row['label'] }}"></span>
                                    @endif
                                @endforeach
                            </div>
                            <ul class="lt-cat-list">
                                @foreach ($weekStats['rows'] as $row)
                                    <li class="lt-cat-row" style="--cat: {{ $row['color'] }};">
                                        <div class="lt-cat-row__head">
                                            <span class="lt-cat-dot" aria-hidden="true"></span>
                                            <span class="lt-cat-row__name">{{ $row['label'] }}</span>
                                            <span class="lt-cat-row__meta">{{ round($row[$metric] / $total * 100) }}%</span>
                                            <span class="lt-cat-row__value">{{ $metric === 'count' ? $row['count'] : $fmt($row['minutes']) }}</span>
                                        </div>
                                        <div class="lt-cat-bar" role="img" aria-label="{{ $row['label'] }}: {{ $metric === 'count' ? $row['count'].' tareas' : $fmt($row['minutes']) }}">
                                            <span style="width: {{ round($row[$metric] / $max * 100) }}%;"></span>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                    @if ($weekStats['unestimated'] > 0)
                        <p class="lt-cat-note" x-show="metric === 'minutes'" x-cloak><i class="bi bi-info-circle"></i> {{ $weekStats['unestimated'] }} sin duración no suman horas.</p>
                    @endif
                </div>
            @endif
        </x-panel>
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
            :secondary="[['label' => 'Crear múltiples tareas', 'icon' => 'bi-list-task', 'event' => 'task-bulk-editor', 'detail' => ['action' => 'openForm'], 'create' => true]]"
            :greeting="config('companion_chat.tasks.greeting')"
            :quick="$chatActions" />
    </div>

    <livewire:task.task-editor :edit-task="$editTask" />
    <livewire:task.task-bulk-editor />

    @include('livewire.task.partials.recurring-completion-dialog')
</div>
