<section class="plan-card" aria-labelledby="plan-tasks-title">
    <header class="plan-card__head">
        <i class="bi bi-list-check" aria-hidden="true"></i>
        <h2 id="plan-tasks-title">Tareas</h2>
    </header>
    <div class="plan-card__body">
        @forelse ($tasks as $task)
            <article class="plan-visit plan-visit--task" wire:key="plan-task-{{ $task->id }}">
                <div class="plan-visit__body">
                    <x-ui.action variant="text" class="plan-task-link"
                            x-on:click="$dispatch('task-editor', { action: 'openForm', id: @js($task->id) })">
                        <x-ui.task-code :code="$task->task_code" />
                        <strong>{{ $task->title }}</strong>
                    </x-ui.action>
                    <span>{{ $task->status?->name ?? ($task->completed ? 'Completada' : 'Pendiente') }}</span>
                </div>
                <x-ui.menu :label="'Más opciones de '.$task->title">
                    <x-ui.menu-item icon="bi-link-45deg" wire:click="unlinkTask('{{ $task->id }}')">Desvincular</x-ui.menu-item>
                </x-ui.menu>
            </article>
        @empty
            <p class="plans-muted">Aún no hay tareas vinculadas.</p>
            <x-ui.action variant="outlined" icon="bi-plus-lg" x-on:click="$dispatch('task-editor', { action: 'openForm' })">Crear tarea</x-ui.action>
            <x-ui.action variant="outlined" icon="bi-link-45deg" wire:click="openTaskLinks">Vincular tareas</x-ui.action>
        @endforelse
    </div>
</section>

<livewire:task.task-editor :plan-context="$plan->id" :key="'plan-task-editor-'.$plan->id" />

@if ($showTaskLinks)
    <x-ui.form-dialog :open="$showTaskLinks" close="closeTaskLinks" :submit-action="$taskOptions ? 'saveTaskLinks' : null"
                      id="plan-task-links" title="Vincular tareas" icon="bi-link-45deg" submit="Vincular">
        @if ($taskOptions)
            <x-ui.multi-select name="selectedTaskIds" label="Tareas" :options="$taskOptions"
                               all-label="Selecciona una o más tareas" icon="bi-list-check" />
        @else
            <p class="plans-muted">No tienes tareas disponibles para vincular.</p>
            <x-slot:actions>
                <x-ui.action variant="text" x-on:click="$dispatch('md-surface-close')">Cerrar</x-ui.action>
            </x-slot:actions>
        @endif
    </x-ui.form-dialog>
@endif
