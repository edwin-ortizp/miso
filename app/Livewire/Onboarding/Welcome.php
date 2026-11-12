<?php

namespace App\Livewire\Onboarding;

use App\Models\HabitDefinition;
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
    public string $context = '';

    public string $intention = '';

    public array $modules = [];

    public function mount(PersonalExperience $experience): void
    {
        $experience->begin((int) auth()->id());
        $state = $experience->state((int) auth()->id());
        $this->context = $state['context'] ?? '';
        $this->intention = $state['intention'] ?? '';
        $this->modules = $state['modules'];
    }

    public function updatedContext(): void
    {
        if (in_array($this->context, ['study', 'both'], true) && ! in_array('pomodoro', $this->modules, true)) {
            $this->modules[] = 'pomodoro';
        }
    }

    public function next(PersonalExperience $experience): void
    {
        $userId = (int) auth()->id();
        $state = $experience->state($userId);
        $step = $state['step'] ?? 1;
        if ($step === 1) {
            $this->validate(['context' => ['required', Rule::in(['study', 'work', 'both', 'other'])],
                'intention' => ['required', 'string', 'max:500']], [], ['context' => 'contexto', 'intention' => 'lo que quieres mejorar']);
        }
        if ($step === 2) {
            $this->validate(['modules' => ['required', 'array', 'contains:habits'], 'modules.*' => [Rule::in(array_keys($experience->catalog()))]],
                ['modules.contains' => 'Mantén Hábitos para configurar tu primer hábito. Después podrás ocultarlo.']);
        }
        $experience->change($userId, function ($state) use ($step) {
            if (($state['step'] ?? 1) !== $step) {
                return $state;
            }
            if ($step === 3 && ! HabitDefinition::where('user_id', auth()->id())->whereKey($state['habit_id'] ?? 0)->exists()) {
                throw ValidationException::withMessages(['habit' => 'Crea tu primer hábito para continuar.']);
            }
            if ($step === 1) {
                $state['context'] = $this->context;
                $state['intention'] = trim($this->intention);
            }
            if ($step === 2) {
                $state['modules'] = array_values(array_unique($this->modules));
            }
            $state['events']['step_'.$step] ??= now()->toIso8601String();
            $state['step'] = min(5, $step + 1);

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

    public function finish(PersonalExperience $experience): void
    {
        $experience->change((int) auth()->id(), function ($state) {
            abort_unless(($state['step'] ?? 1) === 5 && isset($state['context'])
                && HabitDefinition::where('user_id', auth()->id())->whereKey($state['habit_id'] ?? 0)->exists(), 422);
            $state['completed_at'] ??= now()->toIso8601String();
            $state['events']['completed'] ??= $state['completed_at'];

            return $state;
        });
        session()->forget('url.intended');
        $this->redirectRoute('home');
    }

    #[On('onboarding-created')]
    public function refreshSummary(): void {}

    public function render()
    {
        $experience = app(PersonalExperience::class);
        $state = $experience->state((int) auth()->id());

        return view('livewire.onboarding.welcome', ['state' => $state, 'step' => $state['step'] ?? 1,
            'catalog' => $experience->catalog(),
            'habit' => HabitDefinition::where('user_id', auth()->id())->find($state['habit_id'] ?? 0)]);
    }
}
