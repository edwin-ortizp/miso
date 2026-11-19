<?php

namespace App\Livewire\Relationship;

use App\Models\Relationship;
use Illuminate\Support\Collection;
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
