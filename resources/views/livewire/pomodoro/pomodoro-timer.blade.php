@php
    use App\Support\Ui\DataState;

    $sessionsState = $sessions->count() > 0
        ? DataState::CONTENT
        : ($totalSessions > 0 && (count($activeFilters) > 0 || trim($search) !== '') ? DataState::FILTERED_EMPTY : DataState::EMPTY);
    $hoursMinutes = fn (int $seconds) => intdiv($seconds, 3600).' h '.intdiv($seconds % 3600, 60).' min';
    $minutesLabel = fn (int $minutes) => intdiv($minutes, 60).' h '.($minutes % 60).' min';
    $timezone = config('app.timezone');
@endphp

<x-module-shell module="pomodoro" x-data="pomodoroTimer({
    workDuration: {{ $workDuration }},
    shortBreak: {{ $shortBreak }},
    longBreak: {{ $longBreak }},
    storageKey: 'life-tracker:pomodoro:{{ auth()->id() }}',
    saveSession: (startedAt, endedAt, description, clientToken) => $wire.saveSession(startedAt, endedAt, description, clientToken)
})">
    <x-slot:actions>
        <x-module-actions :primary="['label' => 'Registrar tiempo', 'icon' => 'bi-plus-lg', 'action' => 'openForm']" />
    </x-slot:actions>

    <x-slot:controls>
        <p class="md-body-medium mb-0">{{ ucfirst(\Carbon\Carbon::parse($selectedDate)->translatedFormat('l d \d\e F \d\e Y')) }}</p>
    </x-slot:controls>

    <x-slot:rail>
        <x-context-widget :title="$displayDate->isToday() ? 'Meta de hoy' : 'Meta del '.$displayDate->translatedFormat('j \d\e F')" icon="bi-trophy">
            <p class="goal-today"><strong>{{ $minutesLabel($totalMinutes) }} <span>/ {{ $minutesLabel($dailyGoal) }}</span></strong><b>{{ $progressPercentage }} %</b></p>
            <x-ui.progress :value="min($progressPercentage, 100)" tone="primary" label="Avance de la meta diaria" :valueText="$progressPercentage.'% de la meta'" />
            <p class="md-body-small mb-0">
                @if ($totalMinutes < $dailyGoal)
                    Faltan {{ $dailyGoal - $totalMinutes }} min para alcanzar tu meta diaria.
                @elseif ($totalMinutes === $dailyGoal)
                    Alcanzaste tu meta diaria.
                @else
                    Superaste tu meta por {{ $totalMinutes - $dailyGoal }} min.
                @endif
            </p>
        </x-context-widget>

        <x-context-widget title="Calendario del mes" icon="bi-calendar3">
            <x-slot:actions>
                <div class="goal-month-nav">
                    <x-ui.icon-action icon="bi-chevron-left" label="Mes anterior" size="sm" wire:click="previousMonth" />
                    <span>{{ ucfirst($monthData['label']) }}</span>
                    <x-ui.icon-action icon="bi-chevron-right" label="Mes siguiente" size="sm" wire:click="nextMonth" />
                </div>
            </x-slot:actions>
            <x-goal-calendar :month-data="$monthData" :format="$minutesLabel" route="pomodoro" label="Cumplimiento de la meta de enfoque" />
            <p class="goal-streak">
                <span aria-hidden="true">🔥</span>
                @if ($streak > 0)
                    Racha actual: {{ $streak }} {{ $streak === 1 ? 'día' : 'días' }} cumpliendo la meta al 100%.
                @else
                    Aún no tienes racha: cumple tu meta al 100% para empezarla.
                @endif
            </p>
        </x-context-widget>

        <x-context-widget title="Enfoque del mes" icon="bi-stopwatch" tone="success">
            <dl class="md-context-list">
                <div><dt>Tiempo registrado</dt><dd>{{ $minutesLabel($monthData['focus_minutes']) }}</dd></div>
                <div><dt>Metas alcanzadas</dt><dd>{{ $monthData['completed_days'] }} días</dd></div>
            </dl>
        </x-context-widget>
    </x-slot:rail>

    <x-ui.section title="{{ $displayDate->translatedFormat('l d \d\e F') }}"
                  description="Meta: {{ $dailyGoal }} min · {{ max($dailyGoal - $totalMinutes, 0) }} min restantes"
                  :level="2">
        <x-ui.metric-grid label="Resumen de enfoque">
            <x-ui.metric label="Sesiones" icon="bi-check2-circle" tone="success" :value="$sessionCount" unit="sesiones" />
            <x-ui.metric label="Tiempo registrado" icon="bi-stopwatch" tone="info" :value="$hoursMinutes($totalSeconds)" />
        </x-ui.metric-grid>
    </x-ui.section>

    <x-ui.card variant="elevated">
        <div class="pomodoro-stage">
            <div class="md-chip-rail" role="group" aria-label="Modo del temporizador">
                <button type="button" @click="setMode('work')" class="md-chip md-chip-filter pomodoro-mode--work"
                        :class="mode === 'work' ? 'selected' : ''" :aria-pressed="mode === 'work'">Trabajo</button>
                <button type="button" @click="setMode('shortBreak')" class="md-chip md-chip-filter"
                        :class="mode === 'shortBreak' ? 'selected' : ''" :aria-pressed="mode === 'shortBreak'">Descanso corto</button>
                <button type="button" @click="setMode('longBreak')" class="md-chip md-chip-filter"
                        :class="mode === 'longBreak' ? 'selected' : ''" :aria-pressed="mode === 'longBreak'">Descanso largo</button>
            </div>

            <div class="pomodoro-dial">
                <svg viewBox="0 0 200 200" class="pomodoro-dial__svg" aria-hidden="true">
                    <circle cx="100" cy="100" r="90" fill="none" stroke="var(--md-sys-color-surface-container-highest)" stroke-width="8"/>
                    <circle cx="100" cy="100" r="90" fill="none"
                            :stroke="mode === 'work' ? 'var(--md-sys-color-error)' : 'var(--md-sys-color-primary)'" stroke-width="8"
                            :stroke-dasharray="2 * 3.14159 * 90" :stroke-dashoffset="2 * 3.14159 * 90 * (1 - progress)" stroke-linecap="round"/>
                </svg>
                <div class="pomodoro-dial__readout">
                    <span class="md-display-small pomodoro-dial__time" x-text="displayTime" role="timer" aria-live="off"></span>
                    <span class="md-label-medium pomodoro-dial__mode" x-text="mode === 'work' ? 'Trabajo' : 'Descanso'"></span>
                </div>
            </div>

            <div class="pomodoro-controls">
                <button type="button" @click="toggle()" class="md-btn-filled md-btn--lg"
                        :class="mode === 'work' ? 'md-btn--danger' : ''" :disabled="isCompleting">
                    <i class="bi" :class="isRunning ? 'bi-pause-fill' : 'bi-play-fill'" aria-hidden="true"></i>
                    <span x-text="isCompleting ? 'Guardando…' : (isRunning ? 'Pausar' : 'Iniciar')"></span>
                </button>
                <button type="button" @click="reset()" class="md-btn-outlined md-btn--lg"
                        :disabled="isCompleting || pendingCompletion" aria-label="Reiniciar el temporizador">
                    <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
                </button>
            </div>

            <p x-show="errorMessage" x-text="errorMessage" x-cloak class="md-body-small pomodoro-error" role="alert"></p>

            <div class="pomodoro-description">
                <x-ui.field name="pomodoroDescription" label="¿En qué trabajas?" x-model="description" />
            </div>
        </div>
    </x-ui.card>

    <x-ui.management-card id="pomodoro-sessions" title="Sesiones" icon="bi-clock-history"
                          :count="'('.$sessions->total().' / '.$totalSessions.')'" search="search" search-placeholder="Buscar por descripción"
                          sort-model="sort" :sort-options="$sorts" :sort-value="$sort"
                          :active-filters="count($activeFilters)" :paginator="$sessions" noun="sesiones"
                          alpine="draftScope: 'day', draftOrigin: ''"
                          on-filters-open="draftScope = $wire.dateScope; draftOrigin = $wire.originFilter">
        <x-slot:filters>
            <x-ui.select name="filterDateScope" label="Fecha" :options="$dateScopes" :selected="$dateScope" icon="bi-calendar-event" x-model="draftScope" />
            <x-ui.select name="filterOrigin" label="Origen" placeholder="Todos" :options="$origins"
                         :selected="$originFilter" icon="bi-stopwatch" x-model="draftOrigin" />
        </x-slot:filters>
        <x-slot:filterActions>
            <x-ui.action variant="outlined" icon="bi-eraser" wire:click="clearFilters" x-on:click="filtersOpen = false">Limpiar</x-ui.action>
            <x-ui.action variant="filled" icon="bi-funnel-fill" x-on:click="$wire.applyFilters(draftScope, draftOrigin); filtersOpen = false">Filtrar</x-ui.action>
        </x-slot:filterActions>

        @if (count($activeFilters) > 0)
            <x-slot:strip>
                <x-ui.applied-filters :filters="$activeFilters" />
            </x-slot:strip>
        @endif

        @if ($sessionsState === DataState::CONTENT)
            <table class="md-table md-table--stack">
                <thead>
                    <tr>
                        <th scope="col">Fecha</th>
                        <th scope="col">Inicio</th>
                        <th scope="col">Fin</th>
                        <th scope="col">Duración</th>
                        <th scope="col">Descripción</th>
                        <th scope="col">Origen</th>
                        <th scope="col" class="md-table__actions"><span class="visually-hidden">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sessions as $session)
                        @php
                            $start = \Carbon\Carbon::createFromTimestamp($session->start_time['timestamp'] ?? 0, $timezone);
                            $end = \Carbon\Carbon::createFromTimestamp($session->end_time['timestamp'] ?? 0, $timezone);
                        @endphp
                        <tr wire:key="pomodoro-session-{{ $session->id }}">
                            <td class="md-table__date">{{ $session->date->isToday() ? 'Hoy' : $session->date->translatedFormat('j M Y') }}</td>
                            <td class="md-table__date">{{ $start->format('H:i') }}</td>
                            <td class="md-table__date">{{ $end->format('H:i') }}</td>
                            <td class="md-table__nowrap">{{ $hoursMinutes($session->duration) }}</td>
                            <td class="md-table__title">{{ $session->description ?: 'Sin descripción' }}</td>
                            <td><x-ui.chip variant="tonal">{{ $session->client_token ? $origins['timer'] : $origins['manual'] }}</x-ui.chip></td>
                            <td class="md-table__actions">
                                <x-ui.row-actions :label="'Más acciones de la sesión de las '.$start->format('H:i')">
                                    <x-slot:primary wire:click="openForm('{{ $session->id }}')">Editar</x-slot:primary>
                                    <x-ui.menu-divider />
                                    <x-ui.menu-item icon="bi-trash" tone="danger" wire:click="deleteSession('{{ $session->id }}')"
                                                    wire:confirm="La sesión de enfoque se elimina de forma permanente.">Eliminar</x-ui.menu-item>
                                </x-ui.row-actions>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @elseif ($sessionsState === DataState::FILTERED_EMPTY)
            <x-ui.state variant="{{ DataState::FILTERED_EMPTY }}" message="Quita el filtro de fecha o cambia la búsqueda para ver otras sesiones." />
        @else
            <x-ui.state variant="{{ DataState::EMPTY }}" icon="bi-stopwatch" title="Sin sesiones de enfoque"
                        message="Inicia el temporizador o registra un intervalo manual." />
        @endif
    </x-ui.management-card>

    <x-ui.section title="Esta semana"
                  description="{{ $weekStart->format('d M') }} – {{ $weekStart->copy()->endOfWeek()->format('d M') }}"
                  :level="3">
        <x-ui.card variant="elevated">
            <div class="pomodoro-week" role="table" aria-label="Enfoque de la semana">
                @foreach ($weekDays as $day)
                    <div class="pomodoro-week__row {{ $day['date']->isSameDay($selectedDate) ? 'is-selected' : '' }}"
                         role="row" wire:key="pomodoro-week-{{ $day['date']->toDateString() }}">
                        <x-ui.action variant="text" size="sm" wire:click="$set('selectedDate', '{{ $day['date']->toDateString() }}')">
                            {{ $day['date']->translatedFormat('D d') }}
                        </x-ui.action>
                        <span class="pomodoro-week__value md-body-small">{{ $minutesLabel($day['minutes']) }}</span>
                        <span class="pomodoro-week__value md-body-small">Meta {{ $day['goal'] }} min</span>
                        <span class="pomodoro-week__value"><x-ui.chip variant="tonal">{{ $day['percentage'] }}%</x-ui.chip></span>
                    </div>
                @endforeach
            </div>
        </x-ui.card>
    </x-ui.section>

    <x-ui.form-dialog module="pomodoro" :open="$showForm" close="closeForm" submit-action="saveManualSession" id="pomodoro-session-dialog"
                      :title="$editingId ? 'Editar sesión' : 'Registrar tiempo'" icon="bi-stopwatch"
                      :submit="$editingId ? 'Actualizar' : 'Guardar'">
        <div class="d-flex flex-column gap-3">
            @if ($editingId)
                <p class="md-body-small mb-0">
                    Sesión del {{ \Carbon\Carbon::parse($editingDate)->translatedFormat('j \d\e F \d\e Y') }}. Ajusta las horas; si el fin es anterior al inicio, se entiende que cruzó la medianoche.
                </p>
            @else
                <p class="md-body-small mb-0">Indica el intervalo trabajado; la duración se calcula automáticamente.</p>
            @endif
            <div class="md-field-pair">
                <x-ui.field name="manualStart" label="Inicio" :type="$editingId ? 'time' : 'datetime-local'" wire:model.live="manualStart" />
                <x-ui.field name="manualEnd" label="Fin" :type="$editingId ? 'time' : 'datetime-local'" wire:model.live="manualEnd" />
            </div>
            <x-ui.field name="manualDescription" label="Descripción (opcional)" wire:model="manualDescription" />
            @if ($manualDurationSeconds !== null)
                <p class="md-body-medium mb-0" role="status">
                    Duración calculada: <strong>{{ $hoursMinutes($manualDurationSeconds) }}</strong>
                </p>
            @endif
        </div>
    </x-ui.form-dialog>
</x-module-shell>
