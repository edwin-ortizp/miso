<?php

namespace App\Livewire\Task;

class TaskBulkEditor extends TaskEditor
{
    public function openForm(?string $id = null)
    {
        parent::openForm();
    }

    public function save(): void
    {
        $this->saveBulk();
    }

    public function render()
    {
        return view('livewire.task.task-bulk-editor', ['defaults' => $this->formDefaults()]);
    }
}
