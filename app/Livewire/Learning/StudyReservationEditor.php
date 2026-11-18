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
    public ?string $reservation = null;

    #[Locked]
    public ?string $occurrence = null;

    #[Locked]
    public bool $repeats = false;

    public bool $open = false;

    public string $scope = 'one';

    public array $draft = [];

    #[On('study-reservation-create')]
    public function create(): void
    {
        $this->resetValidation();
        $this->reset('reservation', 'occurrence', 'repeats', 'scope');
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

    public function updatedScope(): void
    {
        if ($this->reservation) {
            $this->loadDraft($this->query()->findOrFail($this->reservation));
        }
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
    }

    public function save(): void
    {
        abort_unless($this->open && auth()->check(), 403);
        $single = $this->reservation && $this->repeats && $this->scope === 'one';
        $user = auth()->id();
        $this->draft['formation_id'] = $this->draft['formation_id'] ?: null;
        $this->draft['unit_id'] = $this->draft['unit_id'] ?: null;
        $this->draft['until'] = $this->draft['until'] ?: null;
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
            'draft.task_ids.*' => ['distinct', Rule::exists('tasks', 'id')->where('user_id', $user)],
        ])['draft'];
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
        return view('livewire.learning.study-reservation-editor', [
            'upcoming' => $this->formation ? collect(app(StudySchedule::class)
                ->events((int) $user, today(), today()->addDays(28), $this->formation, $this->unit))
                ->sortBy('start') : [],
            'formations' => $this->open ? LearningFormation::where('user_id', $user)->orderBy('title')->pluck('title', 'id') : [],
            'units' => $this->open && $this->draft['formation_id'] ? LearningUnit::where('user_id', $user)
                ->where('formation_id', $this->draft['formation_id'])->orderBy('title')->pluck('title', 'id') : [],
            'tasks' => $this->open ? Task::where('user_id', $user)->orderBy('title')->pluck('title', 'id') : [],
        ]);
    }
}
