<?php

namespace App\Livewire\Learning;

use App\Services\Learning\AcademicRecords;
use App\Services\Learning\GradeProjection;
use App\Services\Learning\StudyAgenda;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class AcademicEditor extends Component
{
    #[Locked]
    public ?string $context = null;

    #[Locked]
    public string $dialog = '';

    #[Locked]
    public ?string $id = null;

    #[Locked]
    public bool $editing = false;

    public string $unitId = '';

    public bool $graded = false;

    public array $draft = [];

    public array $scenario = [];

    #[Locked]
    public ?array $simulation = null;

    #[On('study-academic-task')]
    public function openTask(string $formation = '', string $semester = ''): void
    {
        if ($this->context) {
            $this->prepare('task', $this->context);

            return;
        }

        $options = app(StudyAgenda::class)->options($this->user(), $formation, $semester);
        $choices = collect($options)->flatMap(fn ($group) => $group);

        $this->prepare('task', $choices->count() === 1 ? (string) $choices->keys()->first() : '');
    }

    #[On('study-academic-edit')]
    public function open(string $type, ?string $id = null): void
    {
        if ($type === 'task') {
            $this->openTask();

            return;
        }
        abort_unless($this->context && in_array($type, ['evaluation', 'score', 'cut', 'book', 'simulate'], true), 404);

        $this->prepare($type, $this->context, $id);
        $records = app(AcademicRecords::class);
        $book = $records->book($this->user(), $this->context);
        if ($type === 'book') {
            $this->draft = ['mode' => $book->mode, 'minimum' => $book->minimum / 100,
                'maximum' => $book->maximum / 100, 'passing' => $book->passing / 100];
        } elseif ($type === 'cut' && $id) {
            $cut = $records->cuts($this->user(), $this->context)->get($id);
            abort_unless($cut, 404);
            $this->draft = ['title' => $cut->title, 'weight' => $cut->weight / 100];
        } elseif ($type === 'simulate') {
            $this->resetScenario();
        } elseif ($type === 'evaluation' && ! $id) {
            $this->draft = ['kind' => 'exam'];
        } elseif (in_array($type, ['evaluation', 'score'], true)) {
            $entry = $records->evaluations($this->user(), $this->context)->get($id);
            abort_unless($entry, 404);
            $this->draft = [
                'title' => $entry->task_title ?? $entry->title,
                'kind' => $entry->kind, 'date' => $entry->scheduled_on,
                'time' => $entry->scheduled_at ? substr($entry->scheduled_at, 0, 5) : null,
                'cut_id' => $entry->cut_id, 'weight' => $entry->weight !== null ? $entry->weight / 100 : null,
                'score' => $entry->score !== null ? $entry->score / 100 : null,
            ];
        }
    }

    private function prepare(string $type, string $unit, ?string $id = null): void
    {
        if ($unit !== '') {
            app(AcademicRecords::class)->subject($this->user(), $unit);
        }
        $this->resetValidation();
        $this->dialog = $type;
        $this->unitId = $unit;
        $this->id = $id ?? (string) Str::uuid();
        $this->graded = false;
        $this->editing = $id !== null;
        $this->draft = match ($type) {
            'task' => ['title' => '', 'date' => '', 'cut_id' => '', 'weight' => ''],
            'cut' => ['title' => '', 'weight' => ''],
            default => [],
        };
        $this->scenario = [];
        $this->simulation = null;
    }

    public function updatedUnitId(): void
    {
        unset($this->draft['cut_id']);
        $this->resetValidation();
    }

    public function save(AcademicRecords $records): void
    {
        abort_unless(in_array($this->dialog, ['task', 'evaluation', 'score', 'book', 'cut'], true) && $this->id, 422);
        $this->validate(['unitId' => 'required|uuid'], ['unitId.required' => 'Elige la materia.']);
        abort_if($this->context && $this->context !== $this->unitId, 404);
        $user = $this->user();
        $unit = $this->unitId;

        try {
            match ($this->dialog) {
                'task' => $records->saveTask($user, $unit, $this->id, $this->draft, $this->graded),
                'book' => $records->saveBook($user, $unit, $this->draft),
                'cut' => $records->saveCut($user, $unit, $this->id, $this->draft),
                'evaluation' => $records->saveEvaluation($user, $unit, $this->id, $this->draft),
                'score' => $records->recordScore($user, $unit, $this->id, $this->draft['score'] ?? null),
            };
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(
                fn ($messages, $field) => ['draft.'.$field => $messages],
            )->all());
        }

        $message = match ($this->dialog) {
            'task' => $this->graded ? 'Tarea y evaluación guardadas.' : 'Tarea guardada.',
            'book' => 'Configuración guardada.', 'cut' => 'Corte guardado.',
            'score' => 'Nota actualizada.', default => 'Evaluación guardada.',
        };
        $this->close();
        $this->dispatch('study-academic-changed', message: $message);
        $this->dispatch('task-calendar-refresh');
    }

    public function simulate(AcademicRecords $records, GradeProjection $projection): void
    {
        abort_unless($this->dialog === 'simulate' && $this->context, 422);
        $this->resetValidation();
        $user = $this->user();
        $book = $records->book($user, $this->context);
        $cuts = $records->cuts($user, $this->context);
        $entries = $records->evaluations($user, $this->context);
        $current = $projection->calculate($book, $cuts, $entries);
        if ($current['state'] === 'setup' || $entries->every(fn ($entry) => $entry->score !== null)) {
            $this->simulation = null;
            $this->addError('simulation', 'Se necesitan ponderaciones completas y al menos una nota pendiente.');

            return;
        }

        $hypothetical = [];
        foreach ($entries->whereNull('score') as $entry) {
            try {
                $hypothetical[$entry->id] = $records->score($book, $this->scenario[$entry->id] ?? null);
            } catch (ValidationException $exception) {
                $this->addError('scenario.'.$entry->id, $exception->validator->errors()->first());
            }
        }
        if ($this->getErrorBag()->isNotEmpty()) {
            $this->simulation = null;

            return;
        }

        $this->simulation = $projection->calculate($book, $cuts, $entries, $hypothetical);
    }

    public function resetScenario(): void
    {
        abort_unless($this->dialog === 'simulate' && $this->context, 422);
        $this->scenario = app(AcademicRecords::class)->evaluations($this->user(), $this->context)
            ->whereNull('score')->mapWithKeys(fn ($entry) => [$entry->id => ''])->all();
        $this->simulation = null;
        $this->resetValidation();
    }

    public function close(): void
    {
        $this->reset('dialog', 'id', 'editing', 'graded', 'draft', 'scenario', 'simulation');
        $this->resetValidation();
    }

    private function user(): int
    {
        abort_unless(auth()->check(), 403);

        return (int) auth()->id();
    }

    public function render(AcademicRecords $records, StudyAgenda $agenda)
    {
        $user = $this->user();
        $subject = $this->dialog && $this->unitId !== '' ? $records->subject($user, $this->context ?? $this->unitId) : null;
        $gradeFields = $this->dialog === 'evaluation' || ($this->dialog === 'task' && $this->graded);
        $book = $subject ? $records->book($user, $subject->id) : null;
        $cuts = $subject && ($gradeFields || $this->dialog === 'simulate') && $book->mode === 'cuts' ? $records->cuts($user, $subject->id) : collect();
        $entries = $subject && in_array($this->dialog, ['evaluation', 'simulate', 'score'], true)
            ? $records->evaluations($user, $subject->id) : collect();

        return view('livewire.learning.academic-editor', [
            'subject' => $subject, 'book' => $book, 'cuts' => $cuts, 'entries' => $entries,
            'entry' => $entries->get($this->id), 'gradeFields' => $gradeFields,
            'subjects' => $this->dialog === 'task' && ! $this->context ? $agenda->options($user) : [],
        ]);
    }
}
