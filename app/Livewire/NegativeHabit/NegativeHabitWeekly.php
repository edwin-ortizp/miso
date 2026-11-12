<?php

namespace App\Livewire\NegativeHabit;

use App\Livewire\Concerns\HasUrlDate;
use App\Models\NegativeHabitDefinition;
use App\Models\NegativeHabitLog;
use Carbon\Carbon;
use Livewire\Component;
use App\Livewire\Concerns\WithManagementCard;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Hábitos Negativos')]
class NegativeHabitWeekly extends Component
{
    use HasUrlDate;
    use WithManagementCard;
    public bool $showLogForm = false;
    public int $selectedHabitId = 0;
    public string $note = '';

    public function mount()
    {
        $this->initializeSelectedDate();
    }

    public function previousWeek()
    {
        $this->selectedDate = Carbon::parse($this->selectedDate)->subWeek()->toDateString();
    }

    public function nextWeek()
    {
        $this->selectedDate = Carbon::parse($this->selectedDate)->addWeek()->toDateString();
    }

    public function thisWeek()
    {
        $this->selectedDate = now()->toDateString();
    }

    public function openLogForm(int $habitId)
    {
        $this->selectedHabitId = $habitId;
        $this->note = '';
        $this->showLogForm = true;
    }

    public function closeLogForm()
    {
        $this->showLogForm = false;
        $this->selectedHabitId = 0;
        $this->note = '';
    }

    public function saveLog()
    {
        if (!$this->selectedHabitId) return;

        NegativeHabitLog::create([
            'habit_id' => $this->selectedHabitId,
            'timestamp' => now()->timestamp,
            'note' => $this->note ?: null,
        ]);

        $this->closeLogForm();
    }

    public function deleteLog(string $id)
    {
        NegativeHabitLog::where('id', $id)->delete();
    }

    public function assignCategory(int $id, string $category): void
    {
        validator(['category' => $category], ['category' => ['required', \Illuminate\Validation\Rule::in(array_keys(\App\Support\CategoryCatalog::options('negative-habits')))]])->validate();
        NegativeHabitDefinition::where('user_id', auth()->id())->findOrFail($id)->update(['category' => $category]);
    }

    public function render()
    {
        $weekStart = Carbon::parse($this->selectedDate)->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();
        $weekDates = [];
        for ($d = $weekStart->copy(); $d->lte($weekEnd); $d->addDay()) {
            $weekDates[] = $d->copy();
        }

        $habits = NegativeHabitDefinition::orderBy('category')->orderBy('name')->paginate($this->perPage());

        $startTimestamp = $weekStart->startOfDay()->timestamp;
        $endTimestamp = $weekEnd->endOfDay()->timestamp;

        $logs = NegativeHabitLog::whereBetween('timestamp', [$startTimestamp, $endTimestamp])
            ->get()
            ->groupBy('habit_id');

        $weeklyCount = $logs->flatten(1)->count();

        return view('livewire.negative-habit.negative-habit-weekly', [
            'habits' => $habits,
            'logs' => $logs,
            'weekDates' => $weekDates,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'weeklyCount' => $weeklyCount,
            // CHG-095: el catálogo se lee una vez por render, no dos veces por hábito.
            'categoryOptions' => \App\Support\CategoryCatalog::options('negative-habits'),
        ]);
    }
}
