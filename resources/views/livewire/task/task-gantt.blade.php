<x-module-shell module="tasks">
    <section class="md-card-elevated md-task-gantt" aria-label="Diagrama de Gantt de tareas" x-data="taskGantt($wire)">
        <div class="md-task-gantt__toolbar">
            <div class="d-flex flex-wrap gap-2" role="group" aria-label="Escala">
                @foreach (['Day' => 'Día', 'Week' => 'Semana', 'Month' => 'Mes'] as $mode => $label)
                    <x-ui.chip variant="filter" :selected="$mode === 'Day'" x-on:click="setMode('{{ $mode }}')"
                        x-bind:class="{ selected: mode === '{{ $mode }}' }" x-bind:aria-pressed="String(mode === '{{ $mode }}')">{{ $label }}</x-ui.chip>
                @endforeach
            </div>
            <button type="button" class="md-btn-text" x-on:click="today()"><i class="bi bi-crosshair" aria-hidden="true"></i> Hoy</button>
        </div>
        <div wire:ignore x-ref="gantt" class="md-task-gantt__surface"></div>
        <p class="md-task-gantt__empty md-body-medium" x-show="empty" x-cloak>No hay tareas programadas.</p>
    </section>

    @if ($unscheduledTasks->isNotEmpty())
        <section class="md-card-filled mt-3">
            <div class="d-flex align-items-center gap-2 mb-2"><i class="bi bi-calendar-x"></i><h2 class="md-title-medium mb-0">Sin fecha</h2></div>
            @foreach ($unscheduledTasks as $task)
                <div class="md-list-item px-0"><button x-on:click="$dispatch('task-editor', {action: 'openForm', id: '{{ $task->id }}'})" class="md-list-item-content md-task-open-button"><span class="md-list-item-headline">{{ $task->title }}</span></button></div>
            @endforeach
        </section>
    @endif

    <div class="lt-fab-zone">
        <x-module-actions fab-always
            :primary="['label' => 'Nueva tarea', 'icon' => 'bi-plus-lg', 'event' => 'task-editor', 'detail' => ['action' => 'openForm']]" />
    </div>

    <livewire:task.task-editor :edit-task="$editTask" />
</x-module-shell>
