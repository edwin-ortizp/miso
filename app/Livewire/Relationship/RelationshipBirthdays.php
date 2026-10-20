<?php

namespace App\Livewire\Relationship;

use App\Models\Relationship;
use App\Models\Task;
use App\Models\TaskAssociation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use App\Livewire\Concerns\WithManagementCard;

#[Layout('layouts.app')]
#[Title('Cumpleaños')]
class RelationshipBirthdays extends Component
{
    use WithManagementCard;
    /** Empty means the rolling twelve-month view instead of a single month. */
    #[Url(as: 'month', history: true, keep: true)]
    public string $monthFilter = '';

    public bool $showTaskForm = false;

    public ?string $taskRelationshipId = null;

    public string $taskTitle = '';

    public ?string $taskDueDate = null;

    public array $monthNames = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
        7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    public function mount(): void
    {
        $this->normalizeFilters();
    }

    public function updatedMonthFilter(): void
    {
        $this->resetPage();
        $this->normalizeFilters();
    }

    public function openTaskForm(string $relationshipId): void
    {
        $relationship = Relationship::findOrFail($relationshipId);
        $birthday = $relationship->birthday();

        $this->taskRelationshipId = $relationship->id;
        $this->taskTitle = 'Preparar el cumpleaños de '.$relationship->displayName();
        $this->taskDueDate = $birthday?->nextOccurrence()->toDateString() ?? today()->toDateString();
        $this->resetValidation();
        $this->showTaskForm = true;
    }

    public function saveTask(): void
    {
        $validated = $this->validate([
            'taskRelationshipId' => ['required', 'string'],
            'taskTitle' => ['required', 'string', 'max:255'],
            'taskDueDate' => ['nullable', 'date'],
        ]);

        $relationship = Relationship::findOrFail($validated['taskRelationshipId']);

        DB::transaction(function () use ($validated, $relationship): void {
            $task = Task::create([
                'task_code' => random_int(10000, 99999),
                'title' => trim($validated['taskTitle']),
                'category' => 'social',
                'end_date' => $validated['taskDueDate'] ?: null,
            ]);

            TaskAssociation::link($task, $relationship);
        });

        $this->showTaskForm = false;
        $this->taskRelationshipId = null;
        $this->taskTitle = '';
        $this->taskDueDate = null;
    }

    protected function filterProperties(): array
    {
        return ['monthFilter'];
    }

    public function render()
    {
        $rows = $this->birthdayRows();

        return view('livewire.relationship.relationship-birthdays', [
            'rows' => $this->paginateCollection($this->monthFilter === '' ? $rows : $this->monthRows($rows)),
            'todayCount' => $rows->where('is_today', true)->count(),
            'totalWithBirthday' => $rows->count(),
        ]);
    }

    /** Every active relationship with a birthday, ordered by its next occurrence. */
    private function birthdayRows(): Collection
    {
        return Relationship::active()
            ->withBirthday()
            ->with('circle')
            ->get()
            ->map(function (Relationship $relationship): ?array {
                $birthday = $relationship->birthday();

                if (! $birthday) {
                    return null;
                }

                return [
                    'relationship' => $relationship,
                    'birthday' => $birthday,
                    'next_occurrence' => $birthday->nextOccurrence(),
                    'days_until' => $birthday->daysUntil(),
                    'is_today' => $birthday->isToday(),
                    'age' => $birthday->ageOnNextOccurrence(),
                ];
            })
            ->filter()
            ->sortBy('days_until')
            ->values();
    }

    private function monthRows(Collection $rows): Collection
    {
        $month = (int) $this->monthFilter;

        return $rows
            ->filter(fn (array $row) => $row['birthday']->month === $month)
            ->sortBy(fn (array $row) => $row['birthday']->day)
            ->values();
    }

    private function normalizeFilters(): void
    {
        if ($this->monthFilter === '') {
            return;
        }

        if (! array_key_exists((int) $this->monthFilter, $this->monthNames)) {
            $this->monthFilter = '';
        }
    }
}
