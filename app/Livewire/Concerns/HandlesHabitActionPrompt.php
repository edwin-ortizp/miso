<?php

namespace App\Livewire\Concerns;

use App\Models\ExerciseType;
use App\Services\HabitGamificationService;
use App\Support\ExerciseMetrics;
use Livewire\Attributes\Locked;
use Illuminate\Validation\ValidationException;

/**
 * El diálogo que pide los datos que faltan cuando un hábito está configurado para
 * «preguntarme al completarlo». Compartido por Hábitos e Inicio, que ofrecen el
 * mismo toggle.
 *
 * Omitir nunca desmarca el hábito: el registro en el otro módulo es opcional.
 */
trait HandlesHabitActionPrompt
{
    public bool $showHabitActionPrompt = false;

    #[Locked]
    public ?int $promptingHabitId = null;

    public string $promptingHabitName = '';

    /** @var array<int, array<string, mixed>> */
    #[Locked]
    public array $habitActionFields = [];

    public bool $habitExerciseSeries = false;

    /** @var array<string, mixed> */
    public array $habitActionInput = [];

    /**
     * @param  array<string, mixed>  $feedback  Lo que devolvió HabitGamificationService::toggle().
     * @return bool Si se abrió el diálogo.
     */
    protected function openHabitActionPrompt(int $habitId, string $name, array $feedback): bool
    {
        if (! ($feedback['requiresInput'] ?? false)) {
            return false;
        }

        $this->resetValidation();
        $this->promptingHabitId = $habitId;
        $this->promptingHabitName = $name;
        $this->habitActionFields = $feedback['promptFields'];
        $this->habitActionInput = collect($feedback['promptFields'])
            ->mapWithKeys(fn (array $field) => [$field['key'] => $field['default']])
            ->all();
        if (in_array($this->habitExerciseProfile(), ['reps', 'weight', 'steps'], true)) {
            $this->habitActionInput['duration'] = null;
        }
        $this->showHabitActionPrompt = true;

        return true;
    }

    public function habitExerciseProfile(): ?string
    {
        if (($this->habitActionFields[0]['key'] ?? null) !== 'exercise_type_id') {
            return null;
        }

        $type = ExerciseType::find($this->habitActionInput['exercise_type_id'] ?? '');

        return $type ? ExerciseMetrics::profile($type) : null;
    }

    public function updatedHabitActionInput(mixed $value, ?string $key = null): void
    {
        if ($key !== 'exercise_type_id' || ($this->habitActionFields[0]['key'] ?? null) !== 'exercise_type_id') {
            return;
        }

        $this->habitActionInput = ['exercise_type_id' => $value, ...array_fill_keys(array_keys(ExerciseMetrics::RULES), null)];
        $this->habitExerciseSeries = false;
        $this->resetValidation();
    }

    public function setHabitExerciseSeries(bool $series): void
    {
        $this->habitExerciseSeries = $series;
    }

    /** «Omitir»: el hábito queda completado, simplemente sin registro asociado. */
    public function skipHabitAction(): void
    {
        $this->closeHabitActionPrompt();
    }

    public function confirmHabitAction(HabitGamificationService $gamification): void
    {
        if (! $this->promptingHabitId) {
            $this->closeHabitActionPrompt();

            return;
        }

        $input = $this->habitActionInput;
        if (($this->habitActionFields[0]['key'] ?? null) === 'exercise_type_id') {
            if (! $this->habitExerciseProfile()) {
                $this->addError('habitActionInput.exercise_type_id', 'Elige un ejercicio de tu catálogo.');

                return;
            }

            $input = array_map(fn ($value) => $value === '' ? null : $value, $input);
            if ($this->habitExerciseSeries) {
                $input['total_reps'] = null;
            } else {
                $input['sets'] = $input['reps'] = null;
            }
        }

        try {
            $result = $gamification->resolveAction(
                $this->promptingHabitId,
                $this->selectedDate,
                $input,
            );
        } catch (ValidationException $exception) {
            // Los errores llegan con la clave del campo; la vista los pinta en su sitio.
            foreach ($exception->errors() as $key => $messages) {
                $this->addError('habitActionInput.'.$key, $messages[0]);
            }

            return;
        }

        if ($result['actionResult']) {
            $this->dispatch('habit-action-registered', message: $result['actionResult']);
        }

        $this->closeHabitActionPrompt();
    }

    private function closeHabitActionPrompt(): void
    {
        $this->showHabitActionPrompt = false;
        $this->promptingHabitId = null;
        $this->promptingHabitName = '';
        $this->habitActionFields = [];
        $this->habitExerciseSeries = false;
        $this->habitActionInput = [];
        $this->resetValidation();
    }
}
