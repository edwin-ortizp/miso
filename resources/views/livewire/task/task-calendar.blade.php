@php
    $createAction = ['label' => 'Nueva tarea', 'icon' => 'bi-plus-lg', 'event' => 'task-editor', 'detail' => ['action' => 'openForm']];
@endphp

<x-module-shell module="tasks" x-on:task-editor-saved.window="$wire.$refresh()" x-data="{ showRecurringDialog: $wire.entangle('showRecurringCompletion') }">
    @if ($lockedNotice)
        <x-ui.snackbar wire:key="locked-notice">
            {{ $lockedNotice }}
            <x-slot:actions>
                <x-ui.icon-action icon="bi-x-lg" size="sm" label="Cerrar aviso" wire:click="$set('lockedNotice', null)" />
            </x-slot:actions>
        </x-ui.snackbar>
    @endif
    <section class="md-card-elevated md-task-calendar" aria-label="Calendario" x-data="taskCalendar($wire, @js(auth()->id()))" @keydown.escape.window="if (actionEvent) { $event.preventDefault(); $event.stopPropagation(); closeActions() }" @resize.window="closeActions()" @scroll.window="closeActions(false)">
        <div class="md-calendar-compact-toolbar" wire:ignore aria-label="Navegación del calendario">
            <h2 class="md-title-medium md-calendar-period" aria-live="polite"><i class="bi bi-calendar4" aria-hidden="true"></i><span x-text="mobileDate"></span></h2>
                <div class="md-calendar-views" role="group" aria-label="Vista del calendario">
                    @foreach (['dayGridMonth' => 'Mes', 'timeGridWeek' => 'Semana', 'timeGridDay' => 'Día', 'listWeek' => 'Agenda'] as $view => $label)
                        <button type="button" class="md-calendar-view-button" :aria-pressed="(viewType === @js($view)).toString()" @click="calendar.changeView(@js($view))">{{ $label }}</button>
                    @endforeach
                </div>
                <div class="md-calendar-view-mobile"><x-ui.select label="Vista:" id="calendar-view" :options="['dayGridMonth' => 'Mes', 'timeGridWeek' => 'Semana', 'timeGridDay' => 'Día', 'listWeek' => 'Agenda']" x-bind:value="viewType" x-on:change="calendar.changeView($event.target.value)" /></div>
            <div class="md-calendar-controls">
                <div class="md-calendar-arrows" role="group" aria-label="Cambiar periodo">
                    <button type="button" class="md-btn-icon" aria-label="Periodo anterior" @click="calendar.prev()"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
                    <button type="button" class="md-btn-icon" aria-label="Periodo siguiente" @click="calendar.next()"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
                </div>
                <x-ui.action variant="tonal" size="sm" class="md-calendar-now" @click="goToNow()">Ahora</x-ui.action>
                <div class="md-calendar-filter-anchor">
                    <x-ui.action variant="outlined" icon="bi-sliders" class="md-calendar-filter-button" x-ref="filterTrigger" data-popover-trigger @click="openFilters()" x-bind:aria-expanded="filtersOpen.toString()" aria-controls="calendar-filters">Filtros <x-ui.badge tone="primary" x-show="hasLayerFilter" x-cloak label="1 filtro aplicado">1</x-ui.badge></x-ui.action>
                    <x-ui.popover state="filtersOpen" title="Filtros" id="calendar-filters">
                        <fieldset><legend class="md-title-small">Tipos de elemento</legend>
                            @foreach (['tasks' => 'Tareas', 'plans' => 'Planes', 'birthdays' => 'Cumpleaños', 'meals' => 'Comidas', 'habits' => 'Hábitos', 'reservations' => 'Reservas', 'health' => 'Salud'] as $layer => $label)
                                <label class="md-checkbox"><input type="checkbox" x-model="draftLayers.{{ $layer }}"> {{ $label }}</label>
                            @endforeach
                        </fieldset>
                        <x-slot:actions><button type="button" class="md-btn-text" @click="resetDraftLayers()">Limpiar</button><button type="button" class="md-btn-filled" @click="applyLayers()">Filtrar</button></x-slot:actions>
                    </x-ui.popover>
                </div>
            </div>
            <div class="md-calendar-applied-filters" x-show="hasLayerFilter" x-cloak>
                <span class="md-calendar-applied-filters__label"><i class="bi bi-funnel" aria-hidden="true"></i> <span class="md-calendar-applied-filters__text">Filtros aplicados:</span></span>
                <x-ui.chip variant="assist" @click="clearLayerFilter()" aria-label="Quitar filtro de tipos de elemento"><span x-text="layerFilterLabel"></span> <i class="bi bi-x-lg" aria-hidden="true"></i></x-ui.chip>
            </div>
        </div>
        <div wire:ignore x-ref="calendar" class="md-task-calendar__surface"></div>
        <template x-teleport="body">
            <div wire:ignore x-ref="actionMenu" class="md-menu__surface md-calendar-action-menu" role="menu" aria-label="Acciones de la tarea"
                 x-cloak x-show="actionEvent"
                 @keydown="menuKey($event)" @click.outside="if (!$event.target.closest('.md-calendar-task-actions')) closeActions()">
                <x-ui.menu-item icon="bi-arrow-right" @click="runAction('advance')" x-bind:disabled="actionBusy"><span x-text="actionEvent?.advanceLabel"></span></x-ui.menu-item>
                <x-ui.menu-item icon="bi-check2-circle" tone="success" x-show="!actionEvent?.completed" @click="runAction('complete')" x-bind:disabled="actionBusy">Completar</x-ui.menu-item>
                <x-ui.menu-item icon="bi-calendar-event" x-show="!actionEvent?.completed" @click="runAction('today')" x-bind:disabled="actionBusy">Mover para hoy</x-ui.menu-item>
                <x-ui.menu-item icon="bi-calendar-plus" x-show="!actionEvent?.completed" @click="runAction('tomorrow')" x-bind:disabled="actionBusy">Mover para mañana</x-ui.menu-item>
                <x-ui.menu-item icon="bi-calendar2-check" x-show="!actionEvent?.completed && !actionEvent?.locked" @click="runAction('findSlot')" x-bind:disabled="actionBusy">Buscar un hueco</x-ui.menu-item>
                <x-ui.menu-divider />
                <x-ui.menu-item icon="bi-pencil" @click="runAction('edit')" x-bind:disabled="actionBusy">Editar</x-ui.menu-item>
                <x-ui.menu-divider />
                <x-ui.menu-item icon="bi-trash" tone="danger" @click="runAction('delete')" x-bind:disabled="actionBusy">Eliminar</x-ui.menu-item>
                <p role="alert" x-show="actionError" x-text="actionError" class="md-body-small"></p>
            </div>
        </template>


    </section>

    <x-ui.management-card id="unscheduled-tasks" :create-actions="[$createAction]" title="Sin fecha" icon="bi-calendar-x" search="unscheduledSearch" search-placeholder="Buscar tareas sin fecha" :refreshable="false" :paginator="$unscheduledTasks" per-page-model="perPage" noun="tareas">
        <div class="table-responsive">
            <table class="md-table align-middle">
                <thead><tr><th scope="col">Tarea</th><th scope="col">Categoría</th><th scope="col">Prioridad</th><th scope="col">Estado</th><th scope="col">Acciones</th></tr></thead>
                <tbody>
                    @forelse ($unscheduledTasks as $task)
                        <tr wire:key="unscheduled-{{ $task->id }}">
                            <td><x-ui.task-code :code="$task->code" /> {{ $task->title }}</td>
                            <td>{{ $unscheduledCategories->get($task->category)?->name ?? 'Sin categoría' }}</td>
                            <td>{{ $priorityLabels[$task->priority] ?? 'Sin prioridad' }}</td>
                            <td>{{ $task->status?->name ?? 'Sin estado' }}</td>
                            <td><x-ui.row-actions :label="'Acciones de '.$task->title">
                                <x-slot:primary x-on:click="document.querySelector('#unscheduled-tasks .md-mcard__search-toggle')?.focus(); $dispatch('task-editor', {action: 'openScheduleForm', id: '{{ $task->id }}', intent: 'schedule', section: 'schedule'})">Programar</x-slot:primary>
                                <x-ui.menu-item icon="bi-check2-circle" x-on:click="document.querySelector('#unscheduled-tasks .md-mcard__search-toggle')?.focus()" wire:click="toggleComplete('{{ $task->id }}')">Completar</x-ui.menu-item>
                                <x-ui.menu-item icon="bi-pencil" x-on:click="$dispatch('task-editor', {action: 'openForm', id: '{{ $task->id }}', mode: 'edit'})">Editar</x-ui.menu-item>
                            </x-ui.row-actions></td>
                        </tr>
                    @empty
                        <tr><td colspan="5">{{ $unscheduledSearch !== '' ? 'No hay tareas que coincidan con la búsqueda.' : 'No hay tareas sin fecha.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.management-card>

    <div class="lt-fab-zone">
        <x-module-actions fab-always
            :primary="$createAction" :split="true"
            :secondary="[['label' => 'Reservar tiempo de estudio', 'icon' => 'bi-calendar-plus', 'event' => 'study-reservation-create', 'create' => true]]" />
    </div>

    <livewire:task.task-editor :edit-task="$editTask" />
    <livewire:task.find-slot />
    <livewire:learning.study-reservation-editor />

    @include('livewire.task.partials.recurring-completion-dialog')

    @if ($notice)
        <x-ui.snackbar wire:key="calendar-notice-{{ $noticeDate }}">
            {{ $notice }}
            <x-slot:actions>
                <button type="button" class="md-btn-text" x-on:click="$dispatch('task-calendar-goto', { date: @js($noticeDate) }); $wire.set('notice', null)">Ver</button>
                <x-ui.icon-action icon="bi-x-lg" size="sm" label="Cerrar aviso" wire:click="$set('notice', null)" />
            </x-slot:actions>
        </x-ui.snackbar>
    @endif
</x-module-shell>
