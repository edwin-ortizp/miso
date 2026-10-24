<div x-data="ltFormEditor($wire, @js($defaults), {openForm:'showForm'})" @task-editor.window="openEditor($event.detail)"
     x-effect="if (!$wire.showForm && $wire.$parent.editTask) $wire.$parent.$set('editTask', null, false)">
    @include('livewire.task.partials.edit-task-dialog', ['dialogId' => 'task-editor', 'dialogTitle' => $editingId ? 'Editar tarea' : 'Nueva tarea', 'showRecurrenceFields' => true, 'localEditor' => true, 'expandedDialog' => $expanded])
</div>
