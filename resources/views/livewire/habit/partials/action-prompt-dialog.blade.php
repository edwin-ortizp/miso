{{--
    Lo que falta por saber cuando un hábito está configurado para «preguntarme al
    completarlo». El hábito ya quedó marcado: Omitir solo renuncia al registro.
--}}
<x-ui.form-dialog :open="$showHabitActionPrompt" close="skipHabitAction" submit-action="confirmHabitAction"
                  id="habit-action-prompt" icon="bi-link-45deg"
                  :title="$promptingHabitName !== '' ? 'Completaste «'.$promptingHabitName.'»' : 'Completar hábito'"
                  submit="Registrar">
    <div class="d-flex flex-column gap-3">
        <p class="md-body-medium mb-0">Cuéntame los detalles para dejarlo registrado. Si prefieres, puedes omitirlo.</p>

        @if (($habitActionFields[0]['key'] ?? null) === 'exercise_type_id')
            <x-ui.combobox name="habitActionInput.exercise_type_id" :label="$habitActionFields[0]['label']"
                           :options="$habitActionFields[0]['options']" wire:model.live="habitActionInput.exercise_type_id" />
            @if ($profile = $this->habitExerciseProfile())
                @include('livewire.exercise.partials.metrics-fields', [
                    'profile' => $profile,
                    'prefix' => 'habitActionInput.',
                    'values' => $habitActionInput,
                    'seriesMode' => $habitExerciseSeries,
                    'seriesAction' => 'setHabitExerciseSeries',
                    'preservedFields' => [],
                ])
            @endif
        @else
            @foreach ($habitActionFields as $field)
                <div wire:key="habit-prompt-{{ $field['key'] }}">
                    @if ($field['type'] === 'select')
                        <x-ui.select :name="'habitActionInput.'.$field['key']" :label="$field['label']"
                                     :options="$field['options']" :selected="data_get($habitActionInput, $field['key'])"
                                     :help="$field['help']" wire:model="habitActionInput.{{ $field['key'] }}" />
                    @else
                        <x-ui.field :name="'habitActionInput.'.$field['key']" :label="$field['label']"
                                    :type="$field['type']" :help="$field['help']"
                                    wire:model="habitActionInput.{{ $field['key'] }}" />
                    @endif
                </div>
            @endforeach

        @endif

        <div>
            <button type="button" class="md-btn-text" wire:click="skipHabitAction">Omitir por esta vez</button>
        </div>
    </div>
</x-ui.form-dialog>
