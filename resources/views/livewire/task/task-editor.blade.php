<div x-data="ltFormEditor($wire, @js($defaults), {openForm:'showForm', openScheduleForm:'showForm'})" x-init="editingContent = !$wire.editingId" @task-editor.window="openEditor($event.detail)" @task-editor-saved.window="editingContent = false"
     x-effect="if (!$wire.showForm && $wire.$parent.editTask) $wire.$parent.$set('editTask', null, false)">
    @include('livewire.task.partials.edit-task-dialog', ['dialogId' => 'task-editor', 'dialogTitle' => $editingId ? 'Editar tarea' : 'Nueva tarea', 'showRecurrenceFields' => true, 'localEditor' => true, 'expandedDialog' => $expanded])
    <div x-data="{ showRecurringDialog: $wire.entangle('showRecurringCompletion') }">
        @include('livewire.task.partials.recurring-completion-dialog')
    </div>
</div>
