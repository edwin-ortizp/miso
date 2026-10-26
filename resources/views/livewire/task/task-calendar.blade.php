<x-module-shell module="tasks" x-data="{ showRecurringDialog: $wire.entangle('showRecurringCompletion') }">
    <section class="md-card-elevated md-task-calendar" aria-label="Calendario de tareas, planes, cumpleaños y comidas" x-data="taskCalendar($wire)">
        <div class="md-calendar-layers" role="group" aria-label="Capas del calendario" wire:ignore>
            @foreach (['tasks' => ['Tareas', 'bi-check2-square', 'task'], 'plans' => ['Planes', 'bi-map', 'plan'], 'birthdays' => ['Cumpleaños', 'bi-cake2', 'birthday'], 'meals' => ['Comidas', 'bi-egg-fried', 'meal']] as $layer => [$label, $icon, $class])
                <button type="button" class="md-btn md-btn-outlined md-calendar-layer md-calendar-layer--{{ $class }}"
                        :aria-pressed="layers.{{ $layer }}.toString()" @click="toggleLayer('{{ $layer }}')">
                    <i class="bi {{ $icon }}" aria-hidden="true"></i> {{ $label }}
                </button>
            @endforeach
        </div>
        <div wire:ignore class="md-calendar-mobile-toolbar" aria-label="Navegación del calendario">
            <h2 class="visually-hidden">Calendario: <span x-text="mobileDate"></span></h2>
            <button type="button" class="md-btn-icon" aria-label="Periodo anterior" @click="calendar.prev()"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
            <div class="md-calendar-view-picker">
                <x-ui.menu label="Cambiar vista del calendario" icon="bi-chevron-down" align="start">
                    <x-ui.menu-item @click="calendar.changeView('timeGridDay')">Día</x-ui.menu-item>
                    <x-ui.menu-item @click="calendar.changeView('timeGridWeek')">Semana</x-ui.menu-item>
                    <x-ui.menu-item @click="calendar.changeView('dayGridMonth')">Mes</x-ui.menu-item>
                    <x-ui.menu-item @click="calendar.changeView('listWeek')">Agenda</x-ui.menu-item>
                </x-ui.menu>
                <span class="md-calendar-view-picker__label" aria-live="polite"><span x-text="mobileDate"></span> · <span x-text="mobileView"></span></span>
            </div>
            <button type="button" class="md-btn-icon" aria-label="Periodo siguiente" @click="calendar.next()"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
            <button type="button" class="md-btn-icon" aria-label="Ahora" title="Ir a hoy y a la hora actual" @click="goToNow()"><i class="bi bi-clock" aria-hidden="true"></i></button>
            <x-ui.menu label="Más opciones del calendario">
                <x-ui.menu-item icon="bi-calendar-event" @click="calendar.today()">Ir a hoy</x-ui.menu-item>
                <x-ui.menu-item icon="bi-arrow-clockwise" @click="calendar.refetchEvents(); $wire.$refresh()" x-bind:disabled="calendarLoading">Actualizar</x-ui.menu-item>
            </x-ui.menu>
        </div>
        <div wire:ignore x-ref="calendar" class="md-task-calendar__surface"></div>

        {{-- Vista rápida y cambios de estado sin abrir el editor. --}}
        <template wire:ignore x-teleport="body">
        <div wire:ignore class="md-task-calendar-anchor" x-ref="anchor">
        <x-ui.popover state="quickOpen" title="Tarea" id="task-calendar-quick" class="md-task-calendar-popover">
            <template x-if="selected">
                <div class="md-task-calendar-popover__info">
                    <p class="md-title-medium mb-0" :class="selected.completed && 'text-decoration-line-through'" x-text="selected.title"></p>
                    <p class="md-body-medium mb-0"><i class="bi bi-clock" aria-hidden="true"></i> <span x-text="selected.timeLabel"></span></p>
                    <p class="md-body-small mb-0" x-show="selected.category"><i class="bi bi-tag" aria-hidden="true"></i> <span x-text="selected.category"></span></p>
                    <p class="md-body-small mb-0" x-show="selected.priority"><i class="bi bi-flag" aria-hidden="true"></i> <span x-text="selected.priority"></span></p>
                    <p class="md-body-small mb-0" x-show="selected.recurrent"><i class="bi bi-arrow-repeat" aria-hidden="true"></i> Se repite</p>
                    <p class="md-body-small mb-0"><i class="bi" :class="selected.status?.icon" aria-hidden="true"></i> Estado: <span x-text="selected.status?.name"></span></p>
                </div>
            </template>
            <p x-show="statusError" x-text="statusError" role="alert" class="md-body-small mb-0"></p>
            <x-slot:actions>
                <button type="button" class="md-btn md-btn-text" x-on:click="edit()" :disabled="busy"><i class="bi bi-pencil" aria-hidden="true"></i> Editar</button>
                <div class="md-task-status-split" @click.outside="statusOpen = false" @resize.window="statusOpen = false"
                     @keydown.escape.stop="if (statusOpen) closeStatusMenu(); else quickOpen = false">
                    <span class="md-btn md-btn-outlined md-task-status-split__label" aria-live="polite">
                        <i class="bi" :class="selected?.status?.icon" aria-hidden="true"></i>
                        <span x-text="selected?.status?.name"></span>
                    </span>
                    <button type="button" class="md-btn md-btn-outlined md-task-status-split__toggle"
                            x-ref="statusTrigger" aria-label="Cambiar estado" aria-haspopup="menu"
                            aria-controls="task-calendar-status-menu" :aria-expanded="(statusOpen && quickOpen).toString()"
                            :disabled="busy" @click="openStatusMenu()" @keydown.arrow-down.prevent="if (!statusOpen) openStatusMenu()">
                        <i class="bi bi-chevron-down" aria-hidden="true"></i>
                    </button>
                    <div id="task-calendar-status-menu" class="md-menu__surface md-task-status-split__menu"
                         x-ref="statusMenu" role="menu" aria-label="Estados de la tarea" x-cloak x-show="statusOpen && quickOpen"
                         @keydown.arrow-down.prevent="moveStatusFocus($event, 1)"
                         @keydown.arrow-up.prevent="moveStatusFocus($event, -1)"
                         @keydown.tab="statusOpen = false">
                        @foreach ($statuses as $status)
                            <button type="button" role="menuitem" class="md-menu__item"
                                    x-show="selected?.status?.id !== @js($status->id)" :disabled="busy || selected?.status?.id === @js($status->id)"
                                    @click="changeStatus(@js($status->id))">
                                <i class="bi {{ $status->icon }}" aria-hidden="true"></i> {{ $status->name }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </x-slot:actions>
        </x-ui.popover>
        </div>
        </template>
    </section>

    @if ($unscheduledTasks->isNotEmpty())
        <section class="md-card-filled mt-3">
            <div class="d-flex align-items-center gap-2 mb-2"><i class="bi bi-calendar-x"></i><h2 class="md-title-medium mb-0">Sin fecha</h2></div>
            @foreach ($unscheduledTasks as $task)
                <div class="md-list-item px-0"><button x-on:click="$dispatch('task-editor', {action: 'openForm', id: '{{ $task->id }}'})" class="md-list-item-content md-task-open-button"><span class="md-list-item-headline"><x-ui.task-code :code="$task->code" /> {{ $task->title }}</span></button></div>
            @endforeach
        </section>
    @endif

    <div class="lt-fab-zone">
        <x-module-actions fab-always
            :primary="['label' => 'Nueva tarea', 'icon' => 'bi-plus-lg', 'event' => 'task-editor', 'detail' => ['action' => 'openForm']]" />
    </div>

    <livewire:task.task-editor :edit-task="$editTask" />

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
