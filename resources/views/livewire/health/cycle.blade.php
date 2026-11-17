@php
    use App\Models\CycleDayLog;
    use App\Support\CycleProgress;

    $dateLabel = fn ($date) => $date->translatedFormat('j M');
    $titles = ['start' => 'Empezó mi periodo', 'end' => 'Terminó mi periodo', 'day' => 'Registrar el día', 'edit' => 'Editar periodo'];
@endphp

<x-module-shell module="health">
    <livewire:game.guardian-card module="health" variant="top" :section-context="$guardianContext" />
    <x-slot:actions>
        <x-module-actions :primary="['label' => $openCycle ? 'Terminó mi periodo' : 'Empezó mi periodo', 'icon' => 'bi-droplet', 'action' => $openCycle ? 'quickEnd' : 'quickStart']"
            :secondary="[
                ['label' => 'Registrar el día', 'icon' => 'bi-journal-plus', 'action' => 'openDay', 'create' => true],
                ['label' => $openCycle ? 'Terminó en otra fecha' : 'Empezó en otra fecha', 'icon' => 'bi-calendar-event', 'action' => $openCycle ? 'openEnd' : 'openStart', 'create' => true],
            ]" />
    </x-slot:actions>

    <p class="md-body-medium" id="cycle-notice">
        MiSo registra tu ciclo y muestra su evolución. No es un dispositivo médico ni un método anticonceptivo. Tus datos de ciclo son privados.
    </p>

    <x-ui.section title="Ciclo actual" :level="2" id="cycle-current">
        @if ($cycles->isEmpty())
            <x-ui.state variant="empty" icon="bi-droplet" title="Aún no has registrado ningún periodo"
                        message="Marca cuándo empezó el último con el botón de abajo." />
        @else
            <p class="md-title-large mb-0">
                <i class="bi bi-droplet" aria-hidden="true"></i>
                @if ($cycleDay) Día {{ $cycleDay }} del ciclo @endif
            </p>
            <p class="md-body-medium mb-0">
                @if ($openCycle)
                    Periodo en curso desde el {{ $openCycle->started_on->translatedFormat('j \d\e F') }}.
                @else
                    Último periodo: {{ $cycles->first()->started_on->translatedFormat('j \d\e F') }}@if ($cycles->first()->periodDays()) · {{ $cycles->first()->periodDays() }} {{ $cycles->first()->periodDays() === 1 ? 'día' : 'días' }}@endif.
                @endif
            </p>
        @endif
    </x-ui.section>

    <x-ui.section title="Próximo periodo (estimación)" :level="2" id="cycle-estimate">
        @if ($summary['predicted_start'])
            <p class="md-body-large mb-0">
                Tiende a empezar entre el {{ $summary['window_from']->translatedFormat('j \d\e F') }} y el {{ $summary['window_to']->translatedFormat('j \d\e F') }}
                (según {{ $summary['intervals'] }} {{ $summary['intervals'] === 1 ? 'ciclo' : 'ciclos' }}).
            </p>
            <p class="md-body-medium mb-0">
                Ciclo promedio {{ $summary['average_cycle'] }} días @if ($summary['average_period']) · periodo promedio {{ $summary['average_period'] }} {{ $summary['average_period'] === 1 ? 'día' : 'días' }}@endif.
                Es una estimación: tu ciclo puede variar.
            </p>
        @else
            <p class="md-body-medium mb-0">Con {{ CycleProgress::MIN_STARTS }} inicios registrados podremos estimar tu próximo periodo.
                @if ($summary['starts'] > 0) Llevas {{ $summary['starts'] }}. @endif</p>
        @endif
    </x-ui.section>

    <x-ui.section :title="ucfirst($monthDate->translatedFormat('F Y'))" :level="2" id="cycle-calendar">
        <x-slot:actions>
            <x-ui.icon-action icon="bi-chevron-left" label="Mes anterior" size="sm" wire:click="previousMonth" />
            <x-ui.icon-action icon="bi-chevron-right" label="Mes siguiente" size="sm" wire:click="nextMonth" />
        </x-slot:actions>
        <div class="md-mcard__scroll" role="region" aria-label="Calendario del ciclo" tabindex="0">
        <table class="md-table" aria-label="Calendario de {{ $monthDate->translatedFormat('F Y') }}">
            <thead>
                <tr>
                    @foreach (['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $dayName)
                        <th scope="col">{{ $dayName }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($weeks as $week)
                    <tr wire:key="week-{{ $week[0]['date']->toDateString() }}">
                        @foreach ($week as $day)
                            <td wire:key="day-{{ $day['date']->toDateString() }}">
                                @if ($day['in_month'])
                                    <button type="button" class="md-btn-text md-btn--sm" wire:click="openDay('{{ $day['date']->toDateString() }}')"
                                            aria-label="{{ $day['date']->translatedFormat('j \d\e F') }}{{ $day['period'] ? ', periodo' : '' }}{{ $day['logged'] ? ', con registro' : '' }}{{ $day['estimated'] ? ', ventana estimada' : '' }}{{ $day['today'] ? ', hoy' : '' }}">
                                        {{ $day['date']->day }}
                                        @if ($day['period']) <i class="bi bi-droplet-fill" aria-hidden="true"></i> @endif
                                        @if ($day['logged']) <i class="bi bi-dot" aria-hidden="true"></i> @endif
                                        @if ($day['estimated'] && ! $day['period']) <i class="bi bi-circle" aria-hidden="true"></i> @endif
                                    </button>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
        <p class="md-body-small mb-0">
            <i class="bi bi-droplet-fill" aria-hidden="true"></i> Periodo ·
            <i class="bi bi-dot" aria-hidden="true"></i> Día registrado ·
            <i class="bi bi-circle" aria-hidden="true"></i> Ventana estimada
        </p>
    </x-ui.section>

    @if ($monthLogs->isNotEmpty())
        <x-ui.section title="Registros del mes" :level="2" id="cycle-day-logs">
            <x-ui.list label="Registros diarios del mes">
                @foreach ($monthLogs as $log)
                    <x-ui.list-item :headline="$log->date->translatedFormat('j \d\e F').($log->flow ? ' · '.CycleDayLog::FLOWS[$log->flow] : '')"
                                    :supporting="implode(', ', $log->symptomLabels()) ?: ($log->notes ?: null)"
                                    wire:key="log-{{ $log->id }}">
                        <x-slot:trailing>
                            <x-ui.icon-action icon="bi-pencil" label="Editar el registro del {{ $log->date->translatedFormat('j \d\e F') }}" wire:click="openDay('{{ $log->date->toDateString() }}')" />
                            <x-ui.destructive-action label="Eliminar el registro del {{ $log->date->translatedFormat('j \d\e F') }}" :iconOnly="true"
                                                     action="deleteDay('{{ $log->id }}')"
                                                     title="Eliminar registro del día"
                                                     message="Se eliminará el registro del {{ $log->date->translatedFormat('j \d\e F') }}." />
                        </x-slot:trailing>
                    </x-ui.list-item>
                @endforeach
            </x-ui.list>
        </x-ui.section>
    @endif

    @if ($cycles->isNotEmpty())
        <x-ui.section :title="'Historial de ciclos ('.$cycles->count().')'" :level="2" id="cycle-history">
            <table class="md-table md-table--stack">
                <thead>
                    <tr>
                        <th scope="col">Inicio</th>
                        <th scope="col">Fin</th>
                        <th scope="col">Periodo</th>
                        <th scope="col">Ciclo</th>
                        <th scope="col" class="md-table__actions"><span class="visually-hidden">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($cycles as $cycle)
                        <tr wire:key="cycle-{{ $cycle->id }}">
                            <td class="md-table__date">{{ $cycle->started_on->translatedFormat('j M Y') }}</td>
                            <td class="md-table__date">{{ $cycle->ended_on ? $cycle->ended_on->translatedFormat('j M Y') : 'En curso' }}</td>
                            <td>{{ $cycle->periodDays() ? $cycle->periodDays().' '.($cycle->periodDays() === 1 ? 'día' : 'días') : '—' }}</td>
                            <td>{{ $lengths[$cycle->id] ? $lengths[$cycle->id].' días' : '—' }}</td>
                            <td class="md-table__actions">
                                <x-ui.icon-action icon="bi-pencil" label="Editar el periodo del {{ $cycle->started_on->translatedFormat('j \d\e F') }}" wire:click="editCycle('{{ $cycle->id }}')" />
                                <x-ui.destructive-action label="Eliminar el periodo del {{ $cycle->started_on->translatedFormat('j \d\e F') }}" :iconOnly="true"
                                                         action="deleteCycle('{{ $cycle->id }}')"
                                                         title="Eliminar periodo"
                                                         message="Se eliminará el periodo que empezó el {{ $cycle->started_on->translatedFormat('j \d\e F') }}. Los registros diarios se conservan." />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-ui.section>
    @endif

    @if ($dialog !== '')
        <div wire:key="cycle-dialog-{{ $dialog }}-{{ $editingId ?? $date }}">
            <x-ui.form-dialog :open="true" close="closeDialog" title="{{ $titles[$dialog] ?? 'Ciclo' }}"
                              submit-action="save" id="cycle-form">
                @if (in_array($dialog, ['start', 'edit'], true))
                    <x-ui.field name="started_on" label="Empezó el" type="date" wire:model="started_on" />
                @endif
                @if (in_array($dialog, ['end', 'edit'], true))
                    <x-ui.field name="ended_on" label="Terminó el" type="date" wire:model="ended_on"
                                :help="$dialog === 'edit' ? 'Déjalo vacío si el periodo sigue en curso.' : null" />
                @endif
                @if ($dialog === 'day')
                    <x-ui.field name="date" label="Día" type="date" wire:model="date" />
                    <x-ui.select name="flow" label="Flujo" placeholder="Sin indicar" :options="$flows" :selected="$flow" wire:model="flow" />
                    <fieldset>
                        <legend class="md-body-medium">Síntomas</legend>
                        @foreach ($symptomOptions as $key => $label)
                            <label class="md-body-medium">
                                <input type="checkbox" value="{{ $key }}" wire:model="symptoms"> {{ $label }}
                            </label>
                        @endforeach
                        @error('symptoms.*') <p class="md-body-small" role="alert">{{ $message }}</p> @enderror
                    </fieldset>
                @endif
                <details>
                    <summary>Más detalle</summary>
                    <x-ui.textarea name="notes" label="Notas" wire:model="notes" />
                    @if (in_array($dialog, ['start', 'day'], true))
                        <x-ui.switch name="is_sensitive" label="Mantener oculto al asistente"
                                     help="Recomendado. El asistente solo lo verá si se lo pides."
                                     :checked="$is_sensitive" wire:model="is_sensitive" />
                    @endif
                </details>
            </x-ui.form-dialog>
        </div>
    @endif
    <x-slot:rail>
        @include('livewire.health.partials.guardian-rail')
    </x-slot:rail>
</x-module-shell>
