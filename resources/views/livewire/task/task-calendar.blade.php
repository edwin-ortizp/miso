<x-module-shell module="tasks">
    <section class="md-card-elevated md-task-calendar" aria-label="Calendario de tareas">
        <div wire:ignore x-data="taskCalendar($wire)" class="md-task-calendar__surface"></div>
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
