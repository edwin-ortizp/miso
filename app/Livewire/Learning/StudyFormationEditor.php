<?php

namespace App\Livewire\Learning;

use App\Services\Learning\LearningRecords;
use Livewire\Attributes\On;
use Livewire\Component;

class StudyFormationEditor extends Component
{
    public bool $open = false;

    public string $type = 'degree';

    public string $title = '';

    #[On('study-dashboard-formation')]
    public function openForm(): void
    {
        $this->reset('type', 'title');
        $this->resetValidation();
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
    }

    public function save(LearningRecords $records): void
    {
        abort_unless(auth()->check() && $this->open, 403);
        $formation = $records->saveFormation((int) auth()->id(), null, [
            'type' => $this->type, 'title' => $this->title, 'status' => 'in_progress',
        ]);

        $this->redirectRoute('learning.show', [$formation->id], navigate: true);
    }

    public function render()
    {
        return view('livewire.learning.study-formation-editor');
    }
}
