<?php

namespace App\Livewire\Habit;

use App\Models\{Goal, GoalHabit, HabitDefinition, HabitAction};
use Livewire\Attributes\{Locked, On};

class HabitEditor extends HabitSettings
{
    #[Locked]
    public string $goalContext = '';

    public function mount(string $goalContext): void
    {
        abort_unless(auth()->check(), 403);
        $this->goalContext = Goal::where('user_id', auth()->id())->findOrFail($goalContext)->id;
    }

    #[On('goal-create-habit')]
    public function createHabit(): void
    {
        $this->openForm();
    }

    protected function afterHabitSaved(HabitDefinition $habit): void
    {
        $goal = Goal::where('user_id', auth()->id())->findOrFail($this->goalContext);
        GoalHabit::link($goal, $habit);
    }

    public function save(): void
    {
        parent::save();
        $this->dispatch('goal-relations-changed');
    }

    public function render()
    {
        return view('livewire.habit.habit-editor', [
            'timesOfDay' => self::TIMES_OF_DAY,
            'actionOptions' => $this->registry()->grouped(),
            'actionFields' => $this->currentFields(),
            'actionModes' => HabitAction::MODES,
        ]);
    }
}
