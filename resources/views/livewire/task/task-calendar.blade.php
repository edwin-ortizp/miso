<x-module-shell module="tasks" x-data="{ showRecurringDialog: $wire.entangle('showRecurringCompletion') }">
    <section class="md-card-elevated md-task-calendar" aria-label="Calendario de tareas" x-data="taskCalendar($wire)">
        <div wire:ignore x-ref="calendar" class="md-task-calendar__surface"></div>

        {{-- Vista rápida al hacer clic en una tarea: completar sin abrir el editor. --}}
        <div class="md-task-calendar-anchor" x-ref="anchor">
        <x-ui.popover state="quickOpen" title="Tarea" id="task-calendar-quick" class="md-task-calendar-popover">
            <template x-if="selected">
                <div class="md-task-calendar-popover__info">
                    <p class="md-title-medium mb-0" :class="selected.completed && 'text-decoration-line-through'" x-text="selected.title"></p>
                    <p class="md-body-medium mb-0"><i class="bi bi-clock" aria-hidden="true"></i> <span x-text="selected.timeLabel"></span></p>
                    <p class="md-body-small mb-0" x-show="selected.category"><i class="bi bi-tag" aria-hidden="true"></i> <span x-text="selected.category"></span></p>
                    <p class="md-body-small mb-0" x-show="selected.priority"><i class="bi bi-flag" aria-hidden="true"></i> <span x-text="selected.priority"></span></p>
                    <p class="md-body-small mb-0" x-show="selected.recurrent"><i class="bi bi-arrow-repeat" aria-hidden="true"></i> Se repite</p>
                    <p class="md-body-small mb-0" x-show="selected.completed"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Completada</p>
                </div>
            </template>
            <x-slot:actions>
                <button type="button" class="md-btn md-btn-text" x-on:click="edit()"><i class="bi bi-pencil" aria-hidden="true"></i> Editar</button>
                <button type="button" class="md-btn md-btn-filled" x-on:click="toggleComplete()" :disabled="busy">
                    <i class="bi" :class="selected?.completed ? 'bi-arrow-counterclockwise' : 'bi-check2'" aria-hidden="true"></i>
                    <span x-text="selected?.completed ? 'Marcar pendiente' : 'Completar'"></span>
                </button>
            </x-slot:actions>
        </x-ui.popover>
        </div>
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
