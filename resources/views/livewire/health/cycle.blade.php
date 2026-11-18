@php
    use App\Models\CycleDayLog;
    use App\Support\CycleProgress;

    $dateLabel = fn ($date) => $date->translatedFormat('j M');
    $titles = ['start' => 'Empezó mi periodo', 'end' => 'Terminó mi periodo', 'day' => 'Registrar el día', 'edit' => 'Editar periodo'];
    $createAction = ['label' => $openCycle ? 'Terminó mi periodo' : 'Empezó mi periodo', 'icon' => 'bi-droplet', 'action' => $openCycle ? 'quickEnd' : 'quickStart'];
    $recordCycleAction = ['label' => $openCycle ? 'Terminó en otra fecha' : 'Empezó en otra fecha', 'icon' => 'bi-calendar-event', 'action' => $openCycle ? 'openEnd' : 'openStart', 'create' => true];
    $otherCreateActions = [
        ['label' => 'Registrar el día', 'icon' => 'bi-journal-plus', 'action' => 'openDay', 'create' => true],
        $recordCycleAction,
    ];
@endphp

<x-module-shell module="health">
    <livewire:game.guardian-card module="health" variant="top" :section-context="$guardianContext" />
    <x-slot:actions>
        <x-module-actions :primary="$createAction"
            :secondary="$otherCreateActions" />
    </x-slot:actions>

    <p class="md-body-small md-cycle-notice" id="cycle-notice"><i class="bi bi-shield-lock" aria-hidden="true"></i> MiSo registra tu ciclo y muestra su evolución. No es un dispositivo médico ni un método anticonceptivo. Tus datos de ciclo son privados.</p>

    <x-ui.card variant="outlined" title="Resumen" icon="bi-droplet" id="cycle-summary">
        @if ($cycles->isEmpty())
            <x-ui.state variant="empty" icon="bi-droplet" title="Aún no has registrado ningún periodo"
                        message="Marca cuándo empezó el último con el botón de abajo." />
        @else
            <dl class="md-cycle-summary">
                <div id="cycle-current">
                    <dt>Ciclo actual</dt>
                    <dd class="md-title-large">@if ($cycleDay) Día {{ $cycleDay }} del ciclo @else — @endif</dd>
                    <dd class="md-body-medium">
                        @if ($openCycle)
                            Periodo en curso desde el {{ $openCycle->started_on->translatedFormat('j \d\e F') }}.
                        @else
                            Último periodo: {{ $cycles->first()->started_on->translatedFormat('j \d\e F') }}@if ($cycles->first()->periodDays()) · {{ $cycles->first()->periodDays() }} {{ $cycles->first()->periodDays() === 1 ? 'día' : 'días' }}@endif.
                        @endif
                    </dd>
                </div>
                <div id="cycle-estimate">
                    <dt>Próximo periodo (estimación)</dt>
                    @if ($summary['predicted_start'])
                        <dd class="md-title-large">{{ $summary['window_from']->translatedFormat('j M') }} – {{ $summary['window_to']->translatedFormat('j M') }}</dd>
                        <dd class="md-body-medium">
                            Tiende a empezar entre el {{ $summary['window_from']->translatedFormat('j \d\e F') }} y el {{ $summary['window_to']->translatedFormat('j \d\e F') }}
                            (según {{ $summary['intervals'] }} {{ $summary['intervals'] === 1 ? 'ciclo' : 'ciclos' }}).
                            Ciclo promedio {{ $summary['average_cycle'] }} días @if ($summary['average_period']) · periodo promedio {{ $summary['average_period'] }} {{ $summary['average_period'] === 1 ? 'día' : 'días' }}@endif.
                            Es una estimación: tu ciclo puede variar.
                        </dd>
                    @else
                        <dd class="md-body-medium">Con {{ CycleProgress::MIN_STARTS }} inicios registrados podremos estimar tu próximo periodo.
                            @if ($summary['starts'] > 0) Llevas {{ $summary['starts'] }}. @endif</dd>
                    @endif
                </div>
            </dl>
        @endif
    </x-ui.card>

    <x-ui.card variant="outlined" :title="ucfirst($monthDate->translatedFormat('F Y'))" icon="bi-calendar3" id="cycle-calendar">
        <x-slot:header>
            <span class="md-card-header__spacer"></span>
            <x-ui.icon-action icon="bi-chevron-left" label="Mes anterior" size="sm" wire:click="previousMonth" />
            <x-ui.icon-action icon="bi-chevron-right" label="Mes siguiente" size="sm" wire:click="nextMonth" />
        </x-slot:header>
        <div class="md-cycle-grid" role="grid" aria-label="Calendario de {{ $monthDate->translatedFormat('F Y') }}">
            @foreach (['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $dayName)
                <span class="md-cycle-grid__head" role="columnheader">{{ $dayName }}</span>
            @endforeach
            @foreach ($weeks as $week)
                @foreach ($week as $day)
                    @if ($day['in_month'])
                        <button type="button" wire:key="day-{{ $day['date']->toDateString() }}" wire:click="openDay('{{ $day['date']->toDateString() }}')"
                                @class(['md-cycle-day', 'is-period' => $day['period'], 'is-estimated' => $day['estimated'] && ! $day['period'], 'is-today' => $day['today'], 'is-logged' => $day['logged']])
                                aria-label="{{ $day['date']->translatedFormat('j \d\e F') }}{{ $day['period'] ? ', periodo' : '' }}{{ $day['logged'] ? ', con registro' : '' }}{{ $day['estimated'] ? ', ventana estimada' : '' }}{{ $day['today'] ? ', hoy' : '' }}">
                            <span class="md-cycle-day__num">{{ $day['date']->day }}</span>
                            <span class="md-cycle-day__marks" aria-hidden="true">
                                @if ($day['period'])<i class="bi bi-droplet-fill"></i>@elseif ($day['estimated'])<i class="bi bi-circle"></i>@endif
                                @if ($day['logged'])<i class="bi bi-dot"></i>@endif
                            </span>
                        </button>
                    @else
                        <span wire:key="pad-{{ $day['date']->toDateString() }}" class="md-cycle-day md-cycle-day--pad" aria-hidden="true"></span>
                    @endif
                @endforeach
            @endforeach
        </div>
        <p class="md-body-small md-cycle-legend mb-0">
            <span><i class="bi bi-droplet-fill" aria-hidden="true"></i> Periodo</span>
            <span><i class="bi bi-dot" aria-hidden="true"></i> Día registrado</span>
            <span><i class="bi bi-circle" aria-hidden="true"></i> Ventana estimada</span>
        </p>
    </x-ui.card>

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
        <x-ui.management-card id="cycle-history" :create-actions="$openCycle ? [] : [$recordCycleAction]" title="Historial de ciclos" icon="bi-droplet"
                              :count="'('.$cycles->count().')'" :paginator="$historyPage" noun="ciclos">
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
                    @foreach ($historyPage as $cycle)
                        <tr wire:key="cycle-{{ $cycle->id }}">
                            <td class="md-table__title md-table__date">{{ $cycle->started_on->translatedFormat('j M Y') }}</td>
                            <td class="md-table__date">@if ($cycle->ended_on){{ $cycle->ended_on->translatedFormat('j M Y') }}@else<x-ui.badge tone="primary">En curso</x-ui.badge>@endif</td>
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
        </x-ui.management-card>
    @endif

    @if ($dialog !== '')
        @php
            $cycleDayErrors = $errors->hasAny(['date', 'flow', 'symptoms', 'symptoms.*']);
            $cycleDetailErrors = $errors->hasAny(['notes']);
            $cycleSubmit = ['start' => 'Guardar inicio', 'end' => 'Guardar fin', 'day' => 'Guardar registro', 'edit' => 'Actualizar periodo'][$dialog] ?? 'Guardar';
        @endphp
        <div wire:key="cycle-dialog-{{ $dialog }}-{{ $editingId ?? $date }}">
            <x-ui.form-dialog :open="true" close="closeDialog" module="health" icon="bi-droplet"
                              title="{{ $titles[$dialog] ?? 'Ciclo' }}" :submit="$cycleSubmit"
                              submit-action="save" id="cycle-form"
                              :sections="$dialog === 'day' ? [
                                  'day' => ['label' => 'Registro', 'icon' => 'bi-droplet', 'error' => $cycleDayErrors],
                                  'details' => ['label' => 'Detalles', 'icon' => 'bi-card-text', 'error' => $cycleDetailErrors],
                              ] : []">
                @if ($dialog === 'day')
                    <x-ui.form-dialog-section name="day" title="Registro del día">
                        <div class="d-flex flex-column gap-3">
                            <x-ui.field name="date" label="Día" type="date" wire:model="date" />
                            <x-ui.select name="flow" label="Flujo" placeholder="Sin indicar" :options="$flows" :selected="$flow" wire:model="flow" />
                            <x-ui.choice-chips name="symptoms" label="Síntomas" :options="$symptomOptions" />
                        </div>
                    </x-ui.form-dialog-section>

                    <x-ui.form-dialog-section name="details" title="Detalles">
                        <div class="d-flex flex-column gap-3">
                            <x-ui.textarea name="notes" label="Notas" wire:model="notes" />
                            <x-ui.switch name="is_sensitive" label="Mantener oculto al asistente"
                                         help="Recomendado. El asistente solo lo verá si se lo pides."
                                         :checked="$is_sensitive" wire:model="is_sensitive" />
                        </div>
                    </x-ui.form-dialog-section>
                @else
                    <div class="d-flex flex-column gap-3">
                        @if (in_array($dialog, ['start', 'edit'], true))
                            <x-ui.field name="started_on" label="Empezó el" type="date" wire:model="started_on" />
                        @endif
                        @if (in_array($dialog, ['end', 'edit'], true))
                            <x-ui.field name="ended_on" label="Terminó el" type="date" wire:model="ended_on"
                                        :help="$dialog === 'edit' ? 'Déjalo vacío si el periodo sigue en curso.' : null" />
                        @endif
                        <x-ui.textarea name="notes" label="Notas (opcional)" wire:model="notes" />
                        @if ($dialog === 'start')
                            <x-ui.switch name="is_sensitive" label="Mantener oculto al asistente"
                                         help="Recomendado. El asistente solo lo verá si se lo pides."
                                         :checked="$is_sensitive" wire:model="is_sensitive" />
                        @endif
                    </div>
                @endif
            </x-ui.form-dialog>
        </div>
    @endif
    <x-slot:rail>
        @include('livewire.health.partials.guardian-rail')
    </x-slot:rail>
</x-module-shell>
