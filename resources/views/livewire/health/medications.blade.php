@php
    use App\Support\Ui\DataState;

    $historyState = $history->count() > 0
        ? DataState::CONTENT
        : ($totalHistory > 0 && count($activeFilters) > 0 ? DataState::FILTERED_EMPTY : DataState::EMPTY);
    $pendingToday = $today->whereIn('state', ['pending', 'overdue'])->count();
    $createAction = ['label' => 'Registrar', 'icon' => 'bi-plus-lg', 'action' => 'openForm'];
    $registerIntakeAction = ['label' => 'Toma puntual', 'icon' => 'bi-check2-circle', 'action' => 'openAdHoc', 'create' => true];
    $otherCreateActions = [
        ['label' => 'Medicamento', 'icon' => 'bi-capsule', 'action' => 'openForm', 'create' => true],
        $registerIntakeAction,
    ];
@endphp

<x-module-shell module="health">
    <livewire:game.guardian-card module="health" variant="top" :section-context="$guardianContext" />
    <x-slot:actions>
        <x-module-actions :primary="$createAction"
            :secondary="$otherCreateActions" />
    </x-slot:actions>

    <p class="md-body-medium" id="medications-notice">MiSo no sustituye la indicación de tu profesional de salud.</p>

    <x-ui.section :title="'Hoy · '.ucfirst(now()->translatedFormat('l j \d\e F'))" :level="2" id="medications-today"
                  :description="$today->isEmpty() ? null : $takenToday.' de '.$today->count().' tomas registradas hoy'">
        @if ($today->isEmpty())
            <x-ui.state variant="{{ DataState::EMPTY }}" icon="bi-capsule"
                        title="{{ $medications->where('is_active', true)->isEmpty() ? 'Aún no tienes medicamentos' : 'No tienes tomas programadas hoy' }}"
                        message="{{ $medications->where('is_active', true)->isEmpty() ? 'Registra el primero con el botón +.' : 'Cuando toque una toma, aparecerá aquí.' }}" />
        @else
            @if ($pendingToday === 0)
                <p class="md-body-medium" role="status">No tienes tomas pendientes hoy.</p>
            @endif
            <x-ui.list label="Tomas de hoy">
                @foreach ($today as $row)
                    @php
                        $med = $row['medication'];
                        $slot = $row['scheduled_for'];
                        $dose = $med->doseText();
                        $label = $med->name.($dose ? ' '.$dose : '').' de las '.$slot->format('H:i');
                    @endphp
                    <x-ui.list-item :headline="$slot->format('H:i').' · '.$med->name.($dose ? ' '.$dose : '')"
                                    :supporting="match ($row['state']) {
                                        'overdue' => 'Atrasada'.($med->instructions ? ' · '.$med->instructions : ''),
                                        'pending' => 'Pendiente'.($med->instructions ? ' · '.$med->instructions : ''),
                                        'taken' => 'Tomada a las '.($row['intake']->taken_at?->format('H:i') ?? $slot->format('H:i')),
                                        'skipped' => 'Omitida'.($row['intake']->skip_reason ? ' · '.$row['intake']->skip_reason : ''),
                                    }"
                                    :completed="in_array($row['state'], ['taken', 'skipped'], true)"
                                    wire:key="today-{{ $med->id }}-{{ $slot->format('Hi') }}">
                        <x-slot:leading>
                            <i class="bi {{ match ($row['state']) { 'taken' => 'bi-check-circle-fill', 'skipped' => 'bi-dash-circle', 'overdue' => 'bi-exclamation-circle', default => 'bi-clock' } }}" aria-hidden="true"></i>
                        </x-slot:leading>
                        <x-slot:trailing>
                            @if (in_array($row['state'], ['pending', 'overdue'], true))
                                <x-ui.action variant="filled" class="md-touch-action" icon="bi-check2" aria-label="Marcar {{ $label }} como tomada"
                                             wire:click="take('{{ $med->id }}', '{{ $slot->format('Y-m-d H:i') }}')">Tomado</x-ui.action>
                                <x-ui.action variant="text" class="md-touch-action" aria-label="Omitir {{ $label }}"
                                             wire:click="openSkip('{{ $med->id }}', '{{ $slot->format('Y-m-d H:i') }}')">Omitir</x-ui.action>
                            @else
                                <x-ui.action variant="text" class="md-touch-action" icon="bi-arrow-counterclockwise" aria-label="Deshacer el registro de {{ $label }}"
                                             wire:click="undo('{{ $row['intake']->id }}')">Deshacer</x-ui.action>
                            @endif
                        </x-slot:trailing>
                    </x-ui.list-item>
                @endforeach
            </x-ui.list>
        @endif
    </x-ui.section>

    @if ($medications->isNotEmpty())
        <x-ui.section title="Mis medicamentos" :level="2" id="my-medications">
            <x-ui.list label="Medicamentos">
                @foreach ($medications as $med)
                    <x-ui.list-item :headline="$med->name.($med->doseText() ? ' '.$med->doseText() : '')"
                                    :supporting="($med->is_active ? '' : 'Finalizado · ').$med->frequencyText().' · desde '.$med->starts_on->translatedFormat('j M Y').($med->ends_on ? ' hasta '.$med->ends_on->translatedFormat('j M Y') : '')"
                                    :completed="! $med->is_active"
                                    wire:key="med-{{ $med->id }}">
                        <x-slot:leading><i class="bi bi-capsule" aria-hidden="true"></i></x-slot:leading>
                        <x-slot:trailing>
                            <x-ui.icon-action icon="bi-pencil" label="Editar {{ $med->name }}" wire:click="edit('{{ $med->id }}')" />
                            @if ($med->is_active)
                                <x-ui.destructive-action label="Finalizar {{ $med->name }}" icon="bi-stop-circle" :iconOnly="true" risk="reversible"
                                                         action="finish('{{ $med->id }}')"
                                                         title="Finalizar medicamento"
                                                         message="Se finalizará «{{ $med->name }}». Su historial de tomas se conserva." />
                            @else
                                <x-ui.icon-action icon="bi-play-circle" label="Reanudar {{ $med->name }}" wire:click="resume('{{ $med->id }}')" />
                            @endif
                            <x-ui.destructive-action label="Eliminar {{ $med->name }}" :iconOnly="true"
                                                     action="deleteMedication('{{ $med->id }}')"
                                                     title="Eliminar medicamento"
                                                     message="Se eliminará «{{ $med->name }}». Las tomas ya registradas se conservan en el historial." />
                        </x-slot:trailing>
                    </x-ui.list-item>
                @endforeach
            </x-ui.list>
        </x-ui.section>
    @endif

    <x-ui.management-card id="medication-history" :create-actions="[$registerIntakeAction]" title="Historial" icon="bi-clock-history"
                          :count="'('.$history->total().' / '.$totalHistory.')'"
                          sort-model="sort" :sort-options="$sorts" :sort-value="$sort"
                          :active-filters="count($activeFilters)" :paginator="$history" noun="tomas"
                          alpine="draftPeriod: ''"
                          on-filters-open="draftPeriod = $wire.period">
        <x-slot:filters>
            <x-ui.select name="filterPeriod" label="Periodo" :options="$periods" :selected="$period" icon="bi-calendar-event" x-model="draftPeriod" />
        </x-slot:filters>
        <x-slot:filterActions>
            <x-ui.action variant="outlined" icon="bi-eraser" wire:click="clearFilters" x-on:click="filtersOpen = false">Limpiar</x-ui.action>
            <x-ui.action variant="filled" icon="bi-funnel-fill" x-on:click="$wire.applyFilters(draftPeriod); filtersOpen = false">Filtrar</x-ui.action>
        </x-slot:filterActions>

        @if (count($activeFilters) > 0)
            <x-slot:strip>
                <x-ui.applied-filters :filters="$activeFilters" />
            </x-slot:strip>
        @endif

        @if ($historyState === DataState::CONTENT)
            <table class="md-table md-table--stack">
                <thead>
                    <tr>
                        <th scope="col">Fecha</th>
                        <th scope="col">Hora</th>
                        <th scope="col">Medicamento</th>
                        <th scope="col">Estado</th>
                        <th scope="col" class="md-table__actions"><span class="visually-hidden">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($history as $intake)
                        @php $when = $intake->occurredAt(); @endphp
                        <tr wire:key="intake-{{ $intake->id }}">
                            <td class="md-table__date">{{ $when->isToday() ? 'Hoy' : $when->translatedFormat('j M Y') }}</td>
                            <td class="md-table__date">{{ $when->format('H:i') }}</td>
                            <td class="md-table__title">
                                {{ $intake->name }}@if ($intake->doseText()) <span class="md-body-small">{{ $intake->doseText() }}</span>@endif
                                @if (! $intake->scheduled_for) <span class="md-body-small">· puntual</span> @endif
                            </td>
                            <td>
                                @if ($intake->status === 'skipped')
                                    <x-ui.badge tone="warning">Omitida</x-ui.badge>
                                    @if ($intake->skip_reason) <span class="md-body-small">{{ $intake->skip_reason }}</span> @endif
                                @else
                                    <x-ui.badge tone="success">Tomada</x-ui.badge>
                                @endif
                            </td>
                            <td class="md-table__actions">
                                <x-ui.destructive-action label="Eliminar la toma de {{ $intake->name }}" :iconOnly="true"
                                                         action="deleteIntake('{{ $intake->id }}')"
                                                         title="Eliminar toma"
                                                         message="Se eliminará la toma de «{{ $intake->name }}» del {{ $when->translatedFormat('j M Y') }}." />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @elseif ($historyState === DataState::FILTERED_EMPTY)
            <x-ui.state variant="{{ DataState::FILTERED_EMPTY }}" title="Sin tomas en este periodo"
                        message="Quita el filtro de periodo para ver otras tomas." />
        @else
            <x-ui.state variant="{{ DataState::EMPTY }}" icon="bi-clock-history" title="Aún no hay tomas registradas"
                        message="Cuando marques una toma, aparecerá aquí." />
        @endif
    </x-ui.management-card>

    @if ($showForm)
        @php
            $medicationErrors = $errors->hasAny(['name', 'dose_amount', 'dose_unit', 'frequency_type', 'times', 'times.*', 'interval_hours', 'weekdays', 'weekdays.*']);
            $medicationDetailErrors = $errors->hasAny(['starts_on', 'ends_on', 'form', 'instructions', 'notes']);
            $weekdayNames = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 0 => 'Domingo'];
            $weekdayShort = [1 => 'L', 2 => 'M', 3 => 'X', 4 => 'J', 5 => 'V', 6 => 'S', 0 => 'D'];
        @endphp
        <div wire:key="medication-form-{{ $editingId ?? 'new' }}">
            <x-ui.form-dialog :open="$showForm" close="closeForm" module="health" icon="bi-capsule"
                              title="{{ $editingId ? 'Editar medicamento' : 'Registrar medicamento' }}"
                              :submit="$editingId ? 'Actualizar medicamento' : 'Guardar medicamento'"
                              submit-action="save" id="medication-form"
                              :sections="[
                                  'medication' => ['label' => 'Medicamento', 'icon' => 'bi-capsule', 'error' => $medicationErrors],
                                  'details' => ['label' => 'Detalles', 'icon' => 'bi-card-text', 'error' => $medicationDetailErrors],
                              ]">
                <x-ui.form-dialog-section name="medication" title="Medicamento" description="Qué tomas, cuánto y cuándo.">
                    <div class="d-flex flex-column gap-3">
                        <x-ui.field name="name" label="Nombre" maxlength="120" wire:model="name" />
                        <div class="md-field-pair">
                            <x-ui.field name="dose_amount" label="Dosis" type="number" inputmode="decimal" step="any" wire:model="dose_amount" />
                            <x-ui.field name="dose_unit" label="Unidad" maxlength="20" help="Por ejemplo: mg, ml, tabletas." wire:model="dose_unit" />
                        </div>
                        <x-ui.select name="frequency_type" label="Frecuencia" :options="$frequencies" :selected="$frequency_type" wire:model.live="frequency_type" />
                        @if ($frequency_type === 'every_hours')
                            <x-ui.field name="interval_hours" label="Cada cuántas horas" type="number" min="1" max="24" step="1" wire:model="interval_hours" />
                        @endif
                        @if ($frequency_type === 'weekdays')
                            <x-ui.choice-chips name="weekdays" label="Días de la semana" shape="round"
                                               :options="$weekdayNames" :short="$weekdayShort" />
                        @endif
                        <fieldset class="md-time-list">
                            <legend class="md-label-medium">Horarios</legend>
                            @foreach ($times as $index => $time)
                                <div class="md-time-list__row" wire:key="medication-time-{{ $index }}">
                                    <x-ui.field :name="'times.'.$index" :label="'Hora '.($index + 1)" type="time" wire:model="times.{{ $index }}" />
                                    @if (count($times) > 1)
                                        <x-ui.icon-action icon="bi-x-lg" label="Quitar hora {{ $index + 1 }}" wire:click="removeTime({{ $index }})" />
                                    @endif
                                </div>
                            @endforeach
                            @error('times') <p class="md-body-small md-choice-chips__error" role="alert">{{ $message }}</p> @enderror
                            @if (count($times) < 12)
                                <x-ui.action variant="text" icon="bi-plus-lg" wire:click="addTime">Agregar hora</x-ui.action>
                            @endif
                        </fieldset>
                    </div>
                </x-ui.form-dialog-section>

                <x-ui.form-dialog-section name="details" title="Detalles" description="Duración, indicaciones y privacidad.">
                    <div class="d-flex flex-column gap-3">
                        <div class="md-field-pair">
                            <x-ui.field name="starts_on" label="Desde" type="date" wire:model="starts_on" />
                            <x-ui.field name="ends_on" label="Hasta (opcional)" type="date" wire:model="ends_on" />
                        </div>
                        <x-ui.field name="form" label="Presentación" maxlength="30" help="Por ejemplo: tableta, jarabe, inyección." wire:model="form" />
                        <x-ui.field name="instructions" label="Indicaciones" maxlength="255" help="Por ejemplo: con comida." wire:model="instructions" />
                        <x-ui.textarea name="notes" label="Notas" wire:model="notes" />
                        <x-ui.switch name="is_sensitive" label="Mantener oculto al asistente"
                                     help="Las consultas amplias del asistente no lo incluirán."
                                     :checked="$is_sensitive" wire:model="is_sensitive" />
                    </div>
                </x-ui.form-dialog-section>
            </x-ui.form-dialog>
        </div>
    @endif

    @if ($showAdHoc)
        <div wire:key="medication-adhoc-form">
            <x-ui.form-dialog :open="$showAdHoc" close="closeAdHoc" module="health" icon="bi-capsule"
                              title="Registrar toma puntual" submit="Registrar toma"
                              submit-action="saveAdHoc" id="medication-adhoc-form">
                <div class="d-flex flex-column gap-3">
                    <x-ui.field name="adhocName" label="Medicamento" maxlength="120" wire:model="adhocName" />
                    <div class="md-field-pair">
                        <x-ui.field name="adhocDoseAmount" label="Dosis" type="number" inputmode="decimal" step="any" wire:model="adhocDoseAmount" />
                        <x-ui.field name="adhocDoseUnit" label="Unidad" maxlength="20" help="Por ejemplo: mg, ml, tabletas." wire:model="adhocDoseUnit" />
                    </div>
                    <x-ui.field name="adhocTakenAt" label="Cuándo la tomaste" type="datetime-local" wire:model="adhocTakenAt" />
                    <x-ui.textarea name="adhocNotes" label="Notas" wire:model="adhocNotes" />
                </div>
            </x-ui.form-dialog>
        </div>
    @endif

    @if ($showSkip)
        <div wire:key="medication-skip-form">
            <x-ui.form-dialog :open="$showSkip" close="closeSkip" module="health" icon="bi-skip-forward" title="Omitir toma" submit="Omitir"
                              submit-action="saveSkip" id="medication-skip-form">
                <x-ui.field name="skipReason" label="Motivo (opcional)" maxlength="255" help="Por ejemplo: náuseas o viaje." wire:model="skipReason" />
            </x-ui.form-dialog>
        </div>
    @endif
    <x-slot:rail>
        @include('livewire.health.partials.guardian-rail')
    </x-slot:rail>
</x-module-shell>
