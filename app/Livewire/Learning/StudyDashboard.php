<?php

namespace App\Livewire\Learning;

use App\Models\LearningFormation;
use App\Models\LearningUnit;
use App\Services\Learning\AcademicRecords;
use App\Services\Learning\StudyAgenda;
use App\Services\TaskGamificationService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Mi estudio')]
class StudyDashboard extends Component
{
    use WithPagination;

    #[Url(as: 'formation', history: true)]
    public string $formationFilter = '';

    #[Url(as: 'semester', history: true)]
    public string $semesterFilter = '';

    #[Locked]
    public string $window = 'week';

    public string $from = '';

    public string $through = '';

    public string $message = '';

    public function updatedFormationFilter(): void
    {
        $this->semesterFilter = '';
        $this->resetPage();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['semesterFilter', 'from', 'through', 'window'], true)) {
            $this->resetPage();
        }
    }

    public function setWindow(string $window): void
    {
        abort_unless(in_array($window, ['week', 'all', 'undated'], true), 422);
        $this->window = $window;
        $this->resetPage();
    }

    #[On('study-academic-changed')]
    public function academicChanged(string $message): void
    {
        $this->message = $message;
        $this->dispatch('study-focus-create');
    }

    #[On('task-editor-saved')]
    public function taskChanged(): void {}

    public function completeTask(string $id, StudyAgenda $agenda, TaskGamificationService $tasks): void
    {
        abort_unless(auth()->check(), 403);
        $result = $agenda->complete((int) auth()->id(), $id, null, $tasks);
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

    public function render(AcademicRecords $records, StudyAgenda $agenda)
    {
        abort_unless(auth()->check(), 403);
        $user = (int) auth()->id();
        $formations = LearningFormation::where('user_id', $user)->whereHas('units', fn ($query) => $query->where('kind', 'subject'))
            ->orderBy('title')->pluck('title', 'id')->all();
        $semesters = LearningUnit::where('user_id', $user)->where('kind', 'period')
            ->when($this->formationFilter !== '', fn ($query) => $query->where('formation_id', $this->formationFilter))
            ->with('formation:id,title')->orderBy('title')->get()->mapWithKeys(fn ($unit) => [
                $unit->id => $unit->title.($this->formationFilter === '' ? ' · '.$unit->formation->title : ''),
            ])->all();

        $entries = $agenda->query($user, $this->formationFilter, $this->semesterFilter);
        if ($this->window === 'undated') {
            $entries->whereNull('agenda.moment');
        } else {
            $entries->whereNotNull('agenda.moment');
            if ($this->window === 'week') {
                $agenda->actionable($entries)->where('agenda.moment', '<', today()->addDays(7)->toDateString());
            } else {
                if ($this->from !== '') {
                    $entries->whereDate('agenda.moment', '>=', $this->from);
                }
                if ($this->through !== '') {
                    $entries->whereDate('agenda.moment', '<=', $this->through);
                }
            }
        }

        return view('livewire.learning.study-dashboard', [
            'formations' => $formations, 'semesters' => $semesters,
            'hasSubjects' => $records->subjects($user)->exists(),
            'entries' => $entries->simplePaginate(12),
            'attention' => $this->window === 'week' ? $agenda->attention($user, $this->formationFilter, $this->semesterFilter) : [],
        ]);
    }
}
