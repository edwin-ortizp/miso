<?php

namespace App\Livewire\Onboarding;

use App\Livewire\Habit\HabitSettings;
use App\Models\HabitDefinition;
use App\Services\Experience\OnboardingChoices;
use App\Services\Experience\PersonalExperience;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;

class HabitEditor extends HabitSettings
{
    #[Locked]
    public bool $createNew = false;

    #[Locked]
    public ?int $startingHabitId = null;

    private ?int $savedHabitId = null;

    #[On('onboarding-habit')]
    public function openGuidedHabit(?string $suggestion = null, bool $createNew = false): void
    {
        $state = app(PersonalExperience::class)->state((int) auth()->id());
        abort_unless(($state['step'] ?? 0) === 3, 403);
        $idea = $suggestion === null ? null : (OnboardingChoices::habits()[$suggestion] ?? null);
        abort_if($suggestion !== null && $idea === null, 422);
        $this->createNew = $createNew || empty($state['habit_id']);
        $this->startingHabitId = $state['habit_id'] ?? null;
        $this->openForm($this->createNew ? null : $state['habit_id']);
        if ($this->createNew) {
            $this->name = $idea['name'] ?? '';
            $this->timeOfDay = $idea['time'] ?? 'anytime';
            $this->icon = $idea['icon'] ?? '🌱';
        }
    }

    public function save(): void
    {
        app(PersonalExperience::class)->change((int) auth()->id(), function ($state) {
            abort_unless(($state['step'] ?? 0) === 3, 403);
            $alreadyCreated = ($state['habit_id'] ?? null) !== $this->startingHabitId;
            $id = $this->createNew && ! $alreadyCreated ? null : ($state['habit_id'] ?? null);
            abort_if($id && ! HabitDefinition::where('user_id', auth()->id())->whereKey($id)->exists(), 422);
            // Only name and moment are editable here. Reload all hidden settings from the record.
            $name = $this->name;
            $time = $this->timeOfDay;
            $icon = $this->icon;
            $this->openForm($id);
            $this->name = trim($name);
            $this->timeOfDay = $time;
            if (! $id) {
                $this->icon = in_array($icon, array_column(OnboardingChoices::habits(), 'icon'), true) ? $icon : '🌱';
            }
            $this->savedHabitId = null;
            if ($id) {
                // Reusing a habit must never synchronize or remove its existing action.
                $this->validate([
                    'name' => ['required', 'string', 'max:120', Rule::unique('habit_definitions', 'name')
                        ->where('user_id', auth()->id())->where('time_of_day', $time)->ignore($id)],
                    'timeOfDay' => ['required', Rule::in(array_keys(self::TIMES_OF_DAY))],
                ], [], ['name' => 'nombre', 'timeOfDay' => 'momento del día']);
                $habit = HabitDefinition::where('user_id', auth()->id())->findOrFail($id);
                $habit->update(['name' => $this->name, 'time_of_day' => $this->timeOfDay]);
                $this->afterHabitSaved($habit);
                $this->closeForm();
            } else {
                parent::save();
            }
            abort_unless($this->savedHabitId !== null, 422);
            $state['habit_id'] = $this->savedHabitId;
            if (! $id) {
                $state['events']['habit_created'] ??= now()->toIso8601String();
            }

            return $state;
        });
        $this->createNew = false;
        $this->dispatch('onboarding-created');
    }

    protected function afterHabitSaved(HabitDefinition $habit): void
    {
        $this->savedHabitId = $habit->id;
    }

    public function render()
    {
        return view('livewire.onboarding.habit-editor', ['timesOfDay' => self::TIMES_OF_DAY]);
    }
}
