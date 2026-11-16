<?php

namespace App\Livewire\Onboarding;

use App\Models\HabitDefinition;
use App\Services\Experience\OnboardingChoices;
use App\Services\Experience\PersonalExperience;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.onboarding')]
#[Title('Tu comienzo en MiSo')]
class Welcome extends Component
{
    public array $contexts = [];

    public string $need = '';

    public string $suggestion = '';

    public bool $moreIdeas = false;

    public string $existingHabit = '';

    public function mount(PersonalExperience $experience): void
    {
        $experience->prepareGuide((int) auth()->id());
        $state = $experience->state((int) auth()->id());
        $this->contexts = $state['contexts'] ?? [];
        $this->need = $state['need'] ?? '';
    }

    public function updatedContexts($value): void
    {
        $last = is_array($value) ? end($value) : $value;
        $this->contexts = $last === 'none' ? ['none'] : array_values(array_diff($this->contexts, ['none']));
    }

    public function next(PersonalExperience $experience): void
    {
        $userId = (int) auth()->id();
        $step = $experience->state($userId)['step'] ?? 1;
        if ($step === 1) {
            $this->validate(['contexts' => ['required', 'array', 'min:1'], 'contexts.*' => ['required', 'string', 'distinct', Rule::in(array_keys(OnboardingChoices::CONTEXTS))]],
                ['contexts.required' => 'Elige al menos una opción.', 'contexts.min' => 'Elige al menos una opción.']);
            if (in_array('none', $this->contexts, true) && count($this->contexts) > 1) {
                throw ValidationException::withMessages(['contexts' => 'Elige Ninguna de estas o las opciones que describen tu día.']);
            }
        }
        if ($step === 2) {
            $this->validate(['need' => ['required', Rule::in(array_keys(OnboardingChoices::NEEDS))]],
                ['need.required' => 'Elige con qué te gustaría empezar.']);
        }
        $experience->change($userId, function ($state) use ($step, $userId) {
            if (($state['step'] ?? 1) !== $step) {
                return $state;
            }
            if ($step === 3 && ! $this->habitExists($userId, $state['habit_id'] ?? null)) {
                throw ValidationException::withMessages(['habit' => 'Prepara o elige un hábito para continuar.']);
            }
            if ($step === 1) {
                $state['contexts'] = array_values($this->contexts);
                $state['context'] = OnboardingChoices::legacyContext($this->contexts);
            }
            if ($step === 2) {
                $state['need'] = $this->need;
                $this->suggestion = '';
                $this->moreIdeas = false;
            }
            $state['events']['v2_step_'.$step] ??= now()->toIso8601String();
            $state['step'] = min(4, $step + 1);

            return $state;
        });
        $this->dispatch('onboarding-step-changed');
    }

    public function previous(PersonalExperience $experience): void
    {
        $experience->change((int) auth()->id(), function ($state) {
            $state['step'] = max(1, ($state['step'] ?? 1) - 1);

            return $state;
        });
        $this->dispatch('onboarding-step-changed');
    }

    public function prepareHabit(PersonalExperience $experience): void
    {
        $state = $experience->state((int) auth()->id());
        abort_unless(($state['step'] ?? 0) === 3, 403);
        $this->validate(['suggestion' => ['required', Rule::in(array_keys(OnboardingChoices::habits()))]],
            ['suggestion.required' => 'Elige una idea o usa Crear el mío.']);
        $this->dispatch('onboarding-habit', suggestion: $this->suggestion, createNew: true);
    }

    public function useExistingHabit(PersonalExperience $experience): void
    {
        $userId = (int) auth()->id();
        $this->validate(['existingHabit' => ['required', 'integer']]);
        $experience->change($userId, function ($state) use ($userId) {
            abort_unless(($state['step'] ?? 0) === 3, 403);
            if (! $this->habitExists($userId, $this->existingHabit)) {
                throw ValidationException::withMessages(['existingHabit' => 'Ese hábito ya no está disponible. Elige otro.']);
            }
            $state['habit_id'] = (int) $this->existingHabit;

            return $state;
        });
        $this->dispatch('onboarding-created');
    }

    /** Permite entrar sin preparar un hábito: se puede crear el primero después, desde Hábitos. */
    public function skipHabit(PersonalExperience $experience): void
    {
        $userId = (int) auth()->id();
        $experience->change($userId, function ($state) {
            abort_unless(($state['step'] ?? 0) === 3, 403);
            $state['habit_skipped'] = true;
            $state['events']['v2_step_3'] ??= now()->toIso8601String();
            $state['events']['skipped_habit'] ??= now()->toIso8601String();
            $state['step'] = 4;

            return $state;
        });
        $this->dispatch('onboarding-step-changed');
    }

    public function finish(PersonalExperience $experience): void
    {
        $userId = (int) auth()->id();
        $experience->change($userId, function ($state) use ($userId) {
            abort_unless(($state['step'] ?? 1) === 4 && ! empty($state['contexts'])
                && (isset(OnboardingChoices::NEEDS[$state['need'] ?? '']) || ! empty($state['completed_at']))
                && ($this->habitExists($userId, $state['habit_id'] ?? null) || ! empty($state['habit_skipped'])), 422);
            $state['completed_at'] ??= now()->toIso8601String();
            $state['events']['completed'] ??= $state['completed_at'];

            return $state;
        });
        session()->forget('url.intended');
        $this->redirectRoute('home');
    }

    #[On('onboarding-created')]
    public function refreshSummary(): void
    {
        $this->suggestion = '';
    }

    private function habitExists(int $userId, $id): bool
    {
        return HabitDefinition::where('user_id', $userId)->whereKey($id ?? 0)->exists();
    }

    public function render()
    {
        $userId = (int) auth()->id();
        $state = app(PersonalExperience::class)->state($userId);

        return view('livewire.onboarding.welcome', [
            'state' => $state, 'step' => $state['step'] ?? 1,
            'contextChoices' => OnboardingChoices::CONTEXTS, 'needChoices' => OnboardingChoices::NEEDS,
            'suggestions' => OnboardingChoices::suggestions($state['need'] ?? 'unsure', $state['contexts'] ?? [], $this->moreIdeas),
            'habit' => HabitDefinition::where('user_id', $userId)->find($state['habit_id'] ?? 0),
            'existingHabits' => HabitDefinition::where('user_id', $userId)->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }
}
