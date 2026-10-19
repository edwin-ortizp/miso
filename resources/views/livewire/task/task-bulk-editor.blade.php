<div x-data="ltFormEditor($wire, @js($defaults), {openForm:'showForm'})" @task-bulk-editor.window="openEditor($event.detail)">
    @include('livewire.task.partials.edit-task-dialog', ['dialogId' => 'task-bulk-editor', 'dialogTitle' => 'Crear múltiples tareas', 'localEditor' => true, 'bulkEditor' => true])
</div>
