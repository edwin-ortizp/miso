<?php

namespace App\Livewire\Learning;

use App\Models\LearningFormation;
use App\Models\LearningUnit;
use App\Models\StudyReservation;
use App\Models\Task;
use App\Services\Calendar\StudySchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class StudyReservationEditor extends Component
{
    #[Locked]
    public ?string $formation = null;

    #[Locked]
    public ?string $unit = null;

    #[Locked]
    public bool $showUpcoming = true;

    #[Locked]
    public ?string $reservation = null;

    #[Locked]
    public ?string $occurrence = null;

    #[Locked]
    public bool $repeats = false;

    public bool $open = false;

    #[Locked]
    public string $scope = 'one';

    public array $draft = [];

    #[Locked]
    public array $originalDraft = [];

    public string $taskSearch = '';

    #[Locked]
    public ?string $validationSection = null;

    #[Locked]
    public int $validationAttempt = 0;

    public const SECTION_FIELDS = [
        'general' => ['draft.title', 'draft.formation_id', 'draft.unit_id'],
        'schedule' => ['scope', 'draft.date', 'draft.starts_at', 'draft.ends_at', 'draft.frequency',
            'draft.interval', 'draft.weekdays', 'draft.weekdays.*', 'draft.until'],
        'tasks' => ['draft.task_ids', 'draft.task_ids.*'],
    ];

    #[On('study-reservation-create')]
    public function create(): void
    {
        $this->resetValidation();
        $this->reset('reservation', 'occurrence', 'repeats', 'scope', 'taskSearch', 'originalDraft');
        $this->draft = [
            'title' => 'Estudiar', 'date' => today()->toDateString(), 'starts_at' => '16:00', 'ends_at' => '17:00',
            'formation_id' => $this->formation, 'unit_id' => $this->unit, 'frequency' => 'none',
            'interval' => 1, 'weekdays' => [], 'until' => null, 'task_ids' => [],
        ];
        $this->open = true;
    }

    #[On('study-reservation-edit')]
    public function edit(string $reservation, string $occurrence): void
    {
        $this->create();
        $series = $this->query()->findOrFail($reservation);
        $this->validateOccurrence($series, $occurrence);
        $this->reservation = $reservation;
        $this->occurrence = $occurrence;
        $this->repeats = $series->frequency !== 'none';
        $this->loadDraft($series);
    }

    public function changeScope(string $scope, bool $confirmed = false): bool
    {
        abort_unless($this->open && $this->reservation && $this->repeats && in_array($scope, ['one', 'series'], true), 422);

        if ($scope === $this->scope) {
            return true;
        }

        if (! $confirmed && $this->draft != $this->originalDraft) {
            return false;
        }

        $this->scope = $scope;
        $this->reset('taskSearch');
        $this->resetValidation();
        $this->loadDraft($this->query()->findOrFail($this->reservation));

        return true;
    }

    private function loadDraft(StudyReservation $series): void
    {
        $this->draft = $series->only($series->getFillable());
        if ($this->scope === 'one') {
            $exception = $series->exceptions()->where('original_date', $this->occurrence)->first();
            abort_if($exception && $exception->date === null, 409);
            $this->draft = array_replace($this->draft, $exception?->changes ?? [], [
                'date' => $exception?->date ?? $this->occurrence,
            ]);
        }
        $this->draft['task_ids'] = Task::where('user_id', auth()->id())->whereIn('id', $this->draft['task_ids'] ?? [])
            ->pluck('id')->all();
        foreach (['starts_at', 'ends_at'] as $key) {
            $this->draft[$key] = substr($this->draft[$key], 0, 5);
        }
        $this->originalDraft = $this->draft;
    }

    public function save(): void
    {
        abort_unless($this->open && auth()->check(), 403);
        $single = $this->reservation && $this->repeats && $this->scope === 'one';
        $user = auth()->id();
        $this->draft['formation_id'] = $this->draft['formation_id'] ?: null;
        $this->draft['unit_id'] = $this->draft['unit_id'] ?: null;
        $this->draft['until'] = $this->draft['until'] ?: null;
        try {
            $data = $this->validate([
                'scope' => 'in:one,series',
                'draft.title' => 'required|string|max:160',
                'draft.date' => 'required|date_format:Y-m-d',
                'draft.starts_at' => 'required|date_format:H:i',
                'draft.ends_at' => 'required|date_format:H:i|after:draft.starts_at',
                'draft.formation_id' => ['nullable', Rule::exists('learning_formations', 'id')->where('user_id', $user)],
                'draft.unit_id' => ['nullable', Rule::exists('learning_units', 'id')->where('user_id', $user)->where('formation_id', $this->draft['formation_id'])],
                'draft.frequency' => 'required|in:none,daily,weekly',
                'draft.interval' => 'required|integer|min:1|max:52',
                'draft.weekdays' => 'array|max:7',
                'draft.weekdays.*' => 'integer|between:1,7|distinct',
                'draft.until' => $single ? 'nullable|date_format:Y-m-d' : 'nullable|date_format:Y-m-d|after_or_equal:draft.date',
                'draft.task_ids' => 'array',
                'draft.task_ids.*' => ['bail', 'required', 'uuid', 'distinct', function ($attribute, $id, $fail) use ($user) {
                    $linked = in_array($id, $this->originalDraft['task_ids'] ?? [], true);
                    if (! Task::where('user_id', $user)->whereKey($id)->when(! $linked, fn ($query) => $query->where('completed', false))->exists()) {
                        $fail('Alguna tarea ya no está disponible. Revisa tu selección.');
                    }
                }],
            ], [
                'draft.ends_at.after' => 'La hora de finalización debe ser posterior a la hora de inicio.',
                'draft.until.after_or_equal' => 'La fecha límite debe ser igual o posterior a la fecha de la reserva.',
            ], [
                'draft.title' => 'título',
                'draft.date' => 'fecha',
                'draft.starts_at' => 'hora de inicio',
                'draft.ends_at' => 'hora de finalización',
                'draft.formation_id' => 'formación',
                'draft.unit_id' => 'unidad',
                'draft.frequency' => 'repetición',
                'draft.interval' => 'intervalo de repetición',
                'draft.weekdays' => 'días de la semana',
                'draft.weekdays.*' => 'día de la semana',
                'draft.until' => 'fecha límite',
                'draft.task_ids' => 'tareas vinculadas',
                'draft.task_ids.*' => 'tarea vinculada',
            ])['draft'];
        } catch (ValidationException $exception) {
            foreach (self::SECTION_FIELDS as $section => $fields) {
                if ($exception->validator->errors()->hasAny($fields)) {
                    $this->validationSection = $section;
                    $this->validationAttempt++;
                    break;
                }
            }
            throw $exception;
        }
        $data['weekdays'] = array_map('intval', $data['weekdays'] ?? []);
        DB::transaction(function () use ($data, $single) {
            if (! $this->reservation) {
                $this->query()->create($data);
                return;
            }
            $series = $this->query()->lockForUpdate()->findOrFail($this->reservation);
            $this->validateOccurrence($series, $this->occurrence);
            abort_if($series->exceptions()->where('original_date', $this->occurrence)->whereNull('date')->exists(), 409);
            if ($single) {
                $series->exceptions()->updateOrCreate(['original_date' => $this->occurrence], [
                    'date' => $data['date'],
                    'changes' => array_intersect_key($data, array_flip(['title', 'starts_at', 'ends_at', 'formation_id', 'unit_id', 'task_ids'])),
                ]);
            } else {
                $series->update($data);
                $series->exceptions()->delete();
            }
        });
        $this->finish();
    }

    public function cancelReservation(): void
    {
        abort_unless($this->open && $this->reservation, 404);
        DB::transaction(function () {
            $series = $this->query()->lockForUpdate()->findOrFail($this->reservation);
            $this->validateOccurrence($series, $this->occurrence);
            if ($this->repeats && $this->scope === 'one') {
                $series->exceptions()->updateOrCreate(['original_date' => $this->occurrence], ['date' => null, 'changes' => null]);
            } else {
                $series->delete();
            }
        });
        $this->finish();
    }

    public function close(): void
    {
        $this->open = false;
    }

    private function finish(): void
    {
        $this->close();
        $this->dispatch('task-calendar-refresh');
        $this->dispatch('study-reservation-saved');
    }

    private function query()
    {
        abort_unless(auth()->check(), 403);

        return StudyReservation::where('user_id', auth()->id());
    }

    private function validateOccurrence(StudyReservation $series, string $date): void
    {
        validator(['date' => $date], ['date' => 'required|date_format:Y-m-d'])->validate();
        abort_unless(app(StudySchedule::class)->occurs($series, Carbon::parse($date)), 404);
    }

    public function updatedDraftFormationId(): void
    {
        $this->draft['unit_id'] = null;
    }

    public function render()
    {
        $user = auth()->id();
        $term = mb_substr(trim($this->taskSearch), 0, 160);
        $matches = $this->open && $user && mb_strlen($term) >= 2
            ? Task::where('user_id', $user)->where('completed', false)
                ->whereRaw("title LIKE ? ESCAPE '!'", ['%'.strtr($term, ['!' => '!!', '%' => '!%', '_' => '!_']).'%'])
                ->orderBy('title')->orderBy('id')->limit(21)->pluck('title', 'id')
            : collect();

        $selectedIds = $this->draft['task_ids'] ?? [];

        return view('livewire.learning.study-reservation-editor', [
            'upcoming' => $this->formation && $this->showUpcoming ? collect(app(StudySchedule::class)
                ->events((int) $user, today(), today()->addDays(28), $this->formation, $this->unit))
                ->sortBy('start') : [],
            'formations' => $this->open ? LearningFormation::where('user_id', $user)->orderBy('title')->pluck('title', 'id') : [],
            'units' => $this->open && $this->draft['formation_id'] ? LearningUnit::where('user_id', $user)
                ->where('formation_id', $this->draft['formation_id'])->orderBy('title')->pluck('title', 'id') : [],
            'taskOptions' => $matches->take(20),
            'taskTerm' => $term,
            'moreTasks' => $matches->count() > 20,
            'selectedTasks' => $this->open && $user && is_array($selectedIds) && $selectedIds
                ? Task::where('user_id', $user)
                    ->whereIn('id', array_filter($selectedIds, 'is_string'))->pluck('title', 'id')
                : [],
        ]);
    }
}
