<x-module-shell module="habits" archetype="settings">
    <x-slot:actions>
        <x-module-actions :primary="['label' => 'Nuevo hábito', 'icon' => 'bi-plus-lg', 'action' => 'openForm']" />
    </x-slot:actions>

    @if ($message)
        <div class="md-card-filled mb-3 py-3" role="status" aria-live="polite">{{ $message }}</div>
    @endif

    <x-ui.management-card id="habit-definitions" title="Tus hábitos" icon="bi-check2-square"
                          :count="'('.$habits->total().' / '.$totalCount.')'"
                          search="search" search-placeholder="Buscar hábito"
                          :active-filters="$timeFilter !== '' ? 1 : 0" :paginator="$habits" noun="hábitos">
        <x-slot:filters>
            <div class="md-chip-rail md-mcard__filter-chips" role="group" aria-label="Filtrar por momento del día">
                <x-ui.chip variant="filter" :selected="$timeFilter === ''" wire:click="$set('timeFilter', '')">Todos</x-ui.chip>
                @foreach ($timesOfDay as $key => $label)
                    <x-ui.chip variant="filter" :selected="$timeFilter === $key" wire:click="$set('timeFilter', '{{ $key }}')">{{ $label }}</x-ui.chip>
                @endforeach
            </div>
        </x-slot:filters>
        @php
            $appliedChips = array_values(array_filter([
                $timeFilter !== '' ? ['key' => 'timeFilter', 'value' => null, 'label' => 'Momento: '.($timesOfDay[$timeFilter] ?? $timeFilter), 'icon' => 'bi-clock'] : null,
            ]));
        @endphp
        @if ($appliedChips !== [])
            <x-slot:strip>
                <x-ui.applied-filters :filters="$appliedChips" />
            </x-slot:strip>
        @endif
        <x-slot:menu>
            <x-ui.menu-item icon="bi-arrow-counterclockwise" wire:click="restoreDefaults">Restaurar hábitos predeterminados</x-ui.menu-item>
        </x-slot:menu>
        <p class="md-mcard__section-label">Un hábito puede además registrar algo en otro módulo al completarlo.</p>

        <x-ui.list label="Hábitos">
            @forelse ($habits as $habit)
                @php
                    $action = $habit->action && $registry->has($habit->action->action_key)
                        ? $registry->find($habit->action->action_key)
                        : null;
                    $supporting = collect([
                        $timesOfDay[$habit->time_of_day] ?? 'En cualquier momento',
                        $habit->base_time,
                        $habit->goal_duration,
                        $habit->completions_count ? $habit->completions_count.' '.($habit->completions_count === 1 ? 'día' : 'días') : 'Sin registros',
                    ])->filter()->implode(' · ');
                @endphp
                <x-ui.list-item :headline="$habit->name" :supporting="$supporting" wire:key="habit-{{ $habit->id }}">
                    <x-slot:leading>
                        <span class="md-list-icon-circle" aria-hidden="true">{{ $habit->icon ?: '✅' }}</span>
                    </x-slot:leading>
                    <x-slot:trailing>
                        @if ($action)
                            <x-ui.badge>
                                <i class="bi {{ $action::icon() }}" aria-hidden="true"></i>
                                {{ $action::moduleLabel() }}@if ($habit->action->mode === \App\Models\HabitAction::MODE_PROMPT) · pregunta @endif
                            </x-ui.badge>
                        @endif
                        <x-ui.row-actions :label="'Más acciones de '.$habit->name">
                            <x-slot:primary wire:click="openForm({{ $habit->id }})">Editar</x-slot:primary>
                            <x-ui.menu-divider />
                            <x-ui.menu-item icon="bi-trash" tone="danger" wire:click="delete({{ $habit->id }})"
                                            wire:confirm="Se eliminará “{{ $habit->name }}” y su historial de completados.">Eliminar</x-ui.menu-item>
                        </x-ui.row-actions>
                    </x-slot:trailing>
                </x-ui.list-item>
            @empty
                @if ($totalCount === 0)
                    <x-ui.state variant="empty" icon="bi-check2-square" title="Aún no tienes hábitos"
                                message="Crea el tuyo o parte de los predeterminados para empezar a registrar tu día.">
                        <x-slot:actions>
                            <x-ui.action variant="filled" icon="bi-arrow-counterclockwise" wire:click="restoreDefaults">Agregar predeterminados</x-ui.action>
                        </x-slot:actions>
                    </x-ui.state>
                @else
                    <x-ui.state variant="filtered-empty" title="Ningún hábito coincide"
                                message="Ajusta la búsqueda o el momento del día para ver el resto." />
                @endif
            @endforelse
        </x-ui.list>
    </x-ui.management-card>

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
</x-module-shell>
