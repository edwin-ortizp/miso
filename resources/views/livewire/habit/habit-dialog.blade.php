    <x-ui.form-dialog :open="$showForm" close="closeForm" submit-action="save" id="habit-dialog"
                      :title="$editingId ? 'Editar hábito' : 'Nuevo hábito'" icon="bi-check2-square"
                      :submit="$editingId ? 'Actualizar' : 'Crear hábito'"
                      :sections="[
                          'habit' => ['label' => 'Hábito', 'icon' => 'bi-check2-square'],
                          'action' => ['label' => 'Al completarlo', 'icon' => 'bi-link-45deg'],
                      ]">
        <x-ui.form-dialog-section name="habit" title="Hábito"
                                  description="Cómo aparece en tu registro diario.">
            <div class="d-flex flex-column gap-3">
                <div class="md-field-pair">
                    <x-ui.field name="icon" label="Emoji" maxlength="16" wire:model="icon" />
                    <x-ui.field name="name" label="Nombre" :required="true" maxlength="120" wire:model="name" />
                </div>
                <x-ui.select name="timeOfDay" label="Momento del día" :required="true"
                             :selected="$timeOfDay" :options="$timesOfDay" wire:model="timeOfDay" />
                <div class="md-field-pair">
                    <x-ui.field name="baseTime" label="Hora base" type="time" wire:model="baseTime"
                                help="Ordena el hábito dentro de su bloque." />
                    <x-ui.field name="goalDuration" label="Duración objetivo" maxlength="30" wire:model="goalDuration"
                                help="Por ejemplo, «10 min»." />
                </div>
            </div>
        </x-ui.form-dialog-section>

        <x-ui.form-dialog-section name="action" title="Al completarlo"
                                  description="Opcional: además de marcarlo, registra algo en otro módulo.">
            <div class="d-flex flex-column gap-3">
                <x-ui.select name="actionKey" label="Módulo relacionado" placeholder="Nada, solo marcarlo"
                             :selected="$actionKey" :options="$actionOptions" wire:model.live="actionKey"
                             help="Elige qué se registra en otro módulo cuando marques este hábito." />

                @if ($actionKey !== '')
                    <x-ui.select name="actionMode" label="¿Cómo se registra?" :required="true"
                                 :selected="$actionMode" :options="$actionModes" wire:model.live="actionMode"
                                 help="«Con un valor por defecto» registra sin abrir ninguna ventana." />

                    @foreach ($actionFields as $field)
                        @php $asked = $actionMode === \App\Models\HabitAction::MODE_PROMPT && $field->ask; @endphp
                        <div wire:key="habit-action-field-{{ $actionKey }}-{{ $field->key }}">
                            @if ($field->type === 'select')
                                <x-ui.select :name="'actionConfig.'.$field->key" :label="$field->label"
                                             :options="$field->options" :selected="data_get($actionConfig, $field->key)"
                                             :disabled="$asked" :placeholder="$asked ? 'Se te preguntará al completar' : null"
                                             :help="$asked ? 'Se te preguntará al completar el hábito.' : $field->help"
                                             wire:model="actionConfig.{{ $field->key }}" />
                            @else
                                <x-ui.field :name="'actionConfig.'.$field->key" :label="$field->label"
                                            :type="$field->type" :disabled="$asked"
                                            :help="$asked ? 'Se te preguntará al completar el hábito.' : $field->help"
                                            wire:model="actionConfig.{{ $field->key }}" />
                            @endif
                        </div>
                    @endforeach
                @endif
            </div>
        </x-ui.form-dialog-section>
    </x-ui.form-dialog>
