<?php

namespace App\Livewire\Learning;

use App\Services\Learning\AcademicRecords;
use App\Services\Learning\GradeProjection;
use App\Services\Learning\StudyAgenda;
use App\Services\TaskGamificationService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class SubjectPanel extends Component
{
    use WithPagination;

    #[Locked]
    public string $unitId;

    #[Locked]
    public string $section;

    #[Locked]
    public string $evaluation = '';

    public string $message = '';

    #[On('study-academic-changed')]
    public function academicChanged(string $message): void
    {
        $this->message = $message;
    }

    #[On('task-editor-saved')]
    public function taskChanged(): void {}

    public function completeTask(string $id, StudyAgenda $agenda, TaskGamificationService $tasks): void
    {
        abort_unless(auth()->check(), 403);
        $result = $agenda->complete((int) auth()->id(), $id, $this->unitId, $tasks);
        if ($result['recurring'] ?? false) {
            $this->dispatch('task-editor', action: 'openForm', id: $id, mode: 'edit');

            return;
        }
        if ($result['completed']) {
            $this->dispatch('task-completed', ...$result);
        }
        $this->message = 'Tarea completada.';
        $this->dispatch('task-records-changed');
        $this->dispatch('task-calendar-refresh');
    }

    public function deleteAcademic(string $type, string $id, AcademicRecords $records): void
    {
        abort_unless(auth()->check() && in_array($type, ['cut', 'evaluation'], true), 403);
        $this->resetValidation();
        try {
            $records->delete((int) auth()->id(), $this->unitId, $type, $id);
        } catch (ValidationException $exception) {
            $this->addError('deletion', $exception->validator->errors()->first());

            return;
        }
        $this->message = $type === 'cut' ? 'Corte eliminado.' : 'Evaluación eliminada.';
        $this->dispatch('task-calendar-refresh');
    }

    public function render(AcademicRecords $records, StudyAgenda $agenda, GradeProjection $projection)
    {
        abort_unless(auth()->check(), 403);
        $user = (int) auth()->id();
        $subject = $records->subject($user, $this->unitId);
        $book = $records->book($user, $subject->id);
        $cuts = $records->cuts($user, $subject->id);
        $evaluations = $this->section === 'tasks' ? collect() : $records->evaluations($user, $subject->id);
        $entries = $agenda->query($user, unit: $subject->id);
        $entries = match ($this->section) {
            'tasks' => $entries->where('agenda.kind', 'task')->reorder('agenda.completed')
                ->orderByRaw('CASE WHEN agenda.moment IS NULL THEN 1 ELSE 0 END')
                ->orderBy('agenda.moment')->orderBy('agenda.id')->simplePaginate(12, pageName: 'tasksPage'),
            'summary' => $agenda->actionable($entries)->whereNotNull('agenda.moment')->limit(1)->get(),
            default => collect(),
        };

        return view('livewire.learning.subject-panel', [
            'subject' => $subject, 'book' => $book, 'cuts' => $cuts, 'evaluations' => $evaluations,
            'projection' => $projection->calculate($book, $cuts, $evaluations), 'entries' => $entries,
        ]);
    }
}
