@php
    use App\Support\Ui\DataState;

    $logsState = $logs->count() > 0
        ? DataState::CONTENT
        : ($totalLogs > 0 && (count($activeFilters) > 0 || trim($search) !== '') ? DataState::FILTERED_EMPTY : DataState::EMPTY);
    $createAction = ['label' => 'Registrar ejercicio', 'icon' => 'bi-plus-lg', 'action' => 'openForm'];
@endphp

<x-module-shell module="exercise">
    <x-slot:actions>
        <x-module-actions :primary="$createAction"
            :greeting="config('companion_chat.exercise.greeting')"
            :quick="$quickExercises->map(function ($type) {
                $timed = \App\Support\ExerciseMetrics::profile($type) === 'duration';
                return [
                    'type' => 'tap', 'id' => 'exercise-'.$type->id, 'emoji' => $type->icon ?? '🏃',
                    'label' => $type->name.($timed ? ' · 30 min' : ''),
                    'aria' => $timed ? 'Registrar 30 minutos de '.$type->name : 'Abrir registro de '.$type->name,
                    'call' => $timed ? 'quickAdd' : 'openQuickExercise',
                    'args' => $timed ? [(string) $type->id, 30] : [(string) $type->id],
                    'reply' => str_replace('{name}', $type->name, config('companion_chat.exercise.reply')),
                ];
            })->values()->all()" />
    </x-slot:actions>

    <x-slot:controls>
        <p class="md-body-medium mb-0">{{ ucfirst(\Carbon\Carbon::parse($selectedDate)->translatedFormat('l d \d\e F')) }}</p>
    </x-slot:controls>

    <livewire:game.guardian-card module="exercise" variant="top" />

    <x-ui.management-card id="exercise-day-logs" :create-actions="[$createAction]" title="Actividades del día" icon="bi-list-ul"
                          :count="'('.$logs->total().' / '.$totalLogs.')'" search="search" search-placeholder="Buscar actividades"
                          sort-model="sort" :sort-options="$sorts" :sort-value="$sort"
                          :active-filters="count($activeFilters)" :paginator="$logs" noun="registros"
                          alpine="draftScope: 'day', draftType: ''"
                          on-filters-open="draftScope = $wire.dateScope; draftType = $wire.typeFilter">
        <x-slot:filters>
            <x-ui.select name="filterDateScope" label="Fecha" :options="$dateScopes" :selected="$dateScope" icon="bi-calendar-event" x-model="draftScope" />
            <x-ui.select name="filterExerciseType" label="Tipo de ejercicio" placeholder="Todos"
                         :options="$exerciseTypes->mapWithKeys(fn ($type) => [$type->id => (in_array($type->id, $favoriteIds, true) ? '★ ' : '').($type->icon ?? '🏃').' '.$type->name])->all()"
                         :selected="$typeFilter" icon="bi-tag" x-model="draftType" />
        </x-slot:filters>
        <x-slot:filterActions>
            <x-ui.action variant="outlined" icon="bi-eraser" wire:click="clearFilters" x-on:click="filtersOpen = false">Limpiar</x-ui.action>
            <x-ui.action variant="filled" icon="bi-funnel-fill" x-on:click="$wire.applyFilters(draftScope, draftType); filtersOpen = false">Filtrar</x-ui.action>
        </x-slot:filterActions>

        @if (count($activeFilters) > 0)
            <x-slot:strip>
                <x-ui.applied-filters :filters="$activeFilters" />
            </x-slot:strip>
        @endif

        @if ($logsState === DataState::CONTENT)
            <table class="md-table md-table--stack">
                <thead>
                    <tr>
                        <th scope="col">Fecha</th>
                        <th scope="col">Actividad</th>
                        <th scope="col">Detalle</th>
                        <th scope="col">Calorías</th>
                        <th scope="col" class="md-table__actions"><span class="visually-hidden">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($logs as $log)
                        @php
                            $logDate = \Carbon\Carbon::parse($log->date);
                            $typeName = $log->exerciseType?->name ?? 'Ejercicio';
                            $pace = $log->paceSecondsPerKm();
                            $detail = collect([
                                $log->duration ? $log->duration.' min' : null,
                                $log->sets !== null && $log->reps !== null ? $log->sets.' × '.$log->reps : null,
                                $log->sets !== null && $log->reps === null ? $log->sets.' series' : null,
                                $log->reps !== null && $log->sets === null ? $log->reps.' reps por serie' : null,
                                $log->totalRepetitions() !== null ? $log->totalRepetitions().' repeticiones' : null,
                                $pace !== null ? \App\Support\ExerciseMetrics::formatPace($pace) : null,
                                $log->weight ? $log->weight.' kg' : null,
                                $log->distance ? $log->distance.' km' : null,
                                $log->steps ? number_format($log->steps, 0, ',', '.').' pasos' : null,
                            ])->filter()->implode(' · ');
                        @endphp
                        <tr wire:key="exercise-{{ $log->id }}">
                            <td class="md-table__date">{{ $logDate->isToday() ? 'Hoy' : $logDate->translatedFormat('j M Y') }}</td>
                            <td class="md-table__title">
                                <span aria-hidden="true">{{ $log->exerciseType?->icon ?? '🏃' }}</span> {{ $typeName }}
                                @if ($log->notes)<span class="md-table__meta">{{ $log->notes }}</span>@endif
                            </td>
                            <td>{{ $detail ?: '—' }}</td>
                            <td class="md-table__nowrap">{{ $log->calories !== null ? number_format($log->calories, 0, ',', '.').' kcal'.(in_array($log->calories_source, ['catalog_rate', 'speed_met'], true) ? ' estimadas' : '') : '—' }}</td>
                            <td class="md-table__actions">
                                <x-ui.row-actions :label="'Más acciones del registro de '.$typeName">
                                    <x-slot:primary wire:click="openForm('{{ $log->id }}')">Editar</x-slot:primary>
                                    <x-ui.menu-divider />
                                    <x-ui.menu-item icon="bi-trash" tone="danger" wire:click="delete('{{ $log->id }}')"
                                                    wire:confirm="El registro de actividad se elimina de forma permanente.">Eliminar</x-ui.menu-item>
                                </x-ui.row-actions>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @elseif ($logsState === DataState::FILTERED_EMPTY)
            <x-ui.state variant="{{ DataState::FILTERED_EMPTY }}" message="Quita el filtro de fecha o cambia la búsqueda para ver otros registros." />
        @else
            <x-ui.state variant="{{ DataState::EMPTY }}" icon="bi-activity" title="Sin ejercicios registrados"
                        message="Registra tu primera actividad para ver aquí el detalle." />
        @endif
    </x-ui.management-card>

    <x-slot:rail>
        @php
            $selectedDay = \Carbon\Carbon::parse($selectedDate);
            $minutes = fn (int $value) => number_format($value, 0, ',', '.').' min';
            $done = (int) $totalDuration;
        @endphp

        <x-context-widget :title="$selectedDay->isToday() ? 'Objetivo de hoy' : 'Objetivo del '.$selectedDay->translatedFormat('j \d\e F')" icon="bi-bullseye">
            <livewire:game.guardian-card module="exercise" variant="rail" />
            <p class="goal-today"><strong>{{ $minutes($done) }} <span>/ {{ $minutes($dailyGoal) }}</span></strong><b>{{ $rawPercentage }} %</b></p>
            <x-ui.progress :value="min($rawPercentage, 100)" tone="primary" label="Avance de la meta diaria" :valueText="$rawPercentage.'% de la meta'" />
            <p class="md-body-small mb-1">
                @if ($done < $dailyGoal)
                    Faltan {{ $minutes($dailyGoal - $done) }} para alcanzar tu meta diaria.
                @elseif ($done === $dailyGoal)
                    Alcanzaste tu meta diaria.
                @else
                    Superaste tu meta por {{ $minutes($done - $dailyGoal) }}.
                @endif
            </p>
            <p class="md-body-small mb-0">{{ number_format($totalCalories, 0, ',', '.') }} kcal · {{ number_format($totalSteps, 0, ',', '.') }} pasos</p>
        </x-context-widget>

        <x-context-widget title="Calendario del mes" icon="bi-calendar3">
            <x-slot:actions>
                <div class="goal-month-nav">
                    <x-ui.icon-action icon="bi-chevron-left" label="Mes anterior" size="sm" wire:click="previousMonth" />
                    <span>{{ ucfirst($monthData['label']) }}</span>
                    <x-ui.icon-action icon="bi-chevron-right" label="Mes siguiente" size="sm" wire:click="nextMonth" />
                </div>
            </x-slot:actions>
            <x-goal-calendar :month-data="$monthData" :goal="$dailyGoal" :format="$minutes" route="exercise" label="Minutos activos" />
            <p class="goal-streak">
                <span aria-hidden="true">🔥</span>
                @if ($streak > 0)
                    Racha actual: {{ $streak }} {{ $streak === 1 ? 'día' : 'días' }} cumpliendo tu meta de actividad.
                @else
                    Aún no tienes racha: cumple tu meta de actividad para empezarla.
                @endif
            </p>
        </x-context-widget>
    </x-slot:rail>

    <x-ui.form-dialog :open="$showForm" close="closeForm" submit-action="save" id="exercise-dialog"
                      :title="$editingId ? 'Editar ejercicio' : 'Registrar ejercicio'" icon="bi-activity"
                      :submit="$editingId ? 'Actualizar' : 'Registrar'">
        @if ($showForm)
            <div class="d-flex flex-column gap-3">
                <x-ui.combobox name="exerciseTypeId" label="Tipo de ejercicio" placeholder="Buscar ejercicio"
                               :options="$exerciseTypes->mapWithKeys(fn ($type) => [$type->id => ($type->icon ?? '🏃').' '.$type->name])->all()"
                               wire:model.live="exerciseTypeId" />
                @if ($exerciseProfile)
                    @include('livewire.exercise.partials.metrics-fields', [
                        'profile' => $exerciseProfile,
                        'prefix' => '',
                        'values' => ['duration' => $duration, 'distance' => $distance, 'sets' => $sets, 'reps' => $reps],
                        'seriesMode' => $seriesMode,
                        'seriesAction' => 'setSeriesMode',
                        'preservedFields' => $editingId ? array_keys(array_filter([
                            'distance' => $distance, 'duration' => $duration, 'weight' => $weight, 'steps' => $steps,
                            'total_reps' => $total_reps, 'calories' => $calories, 'notes' => $notes,
                        ], fn ($value) => $value !== null && $value !== '')) : [],
                    ])
                @endif
            </div>
        @endif
    </x-ui.form-dialog>
</x-module-shell>
