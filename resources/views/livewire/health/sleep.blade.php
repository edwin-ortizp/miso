@php
    use App\Models\SleepLog;
    use App\Support\SleepProgress;
    use App\Support\Ui\DataState;

    $listState = $history->count() > 0
        ? DataState::CONTENT
        : ($totalLogs > 0 && count($activeFilters) > 0 ? DataState::FILTERED_EMPTY : DataState::EMPTY);
    $goalText = SleepLog::formatMinutes($goal);
@endphp

<x-module-shell module="health">
    <livewire:game.guardian-card module="health" variant="top" :section-context="$guardianContext" />
    <x-slot:actions>
        <x-module-actions :primary="['label' => 'Registrar', 'icon' => 'bi-plus-lg', 'action' => 'openForm']"
            :secondary="[['label' => 'Meta de horas', 'icon' => 'bi-bullseye', 'action' => 'openGoal']]" />
    </x-slot:actions>

    <p class="md-body-medium" id="sleep-notice">
        MiSo registra tu sueño y muestra su evolución. No es un dispositivo médico ni reemplaza a tu profesional de salud.
    </p>

    @if ($last)
        @php
            $minutes = $last->durationMinutes();
            $percentage = $goal > 0 ? (int) round($minutes / $goal * 100) : 0;
        @endphp
        <x-ui.section title="Última noche" :level="2" id="sleep-last" :description="'Meta: '.$goalText">
            <x-slot:actions>
                <x-ui.action variant="text" icon="bi-bullseye" wire:click="openGoal">Meta de horas</x-ui.action>
            </x-slot:actions>
            <p class="md-title-large mb-0">
                <i class="bi bi-moon-stars" aria-hidden="true"></i> <strong>{{ $last->durationText() }}</strong>
            </p>
            <p class="md-body-medium mb-0">
                {{ $last->slept_at->format('H:i') }} → {{ $last->woke_at->format('H:i') }}
                · {{ $last->woke_at->isToday() ? 'Hoy' : $last->woke_at->translatedFormat('j M Y') }}
                @if ($last->quality) · Calidad {{ $last->quality }}/5 @endif
            </p>
            <x-ui.progress :value="min($percentage, 100)" tone="primary" label="Avance de tu meta de sueño" :valueText="$percentage.'% de tu meta'" />
            <p class="md-body-small mb-0">{{ SleepProgress::goalMessage($minutes, $goal) }}.</p>
        </x-ui.section>

        <x-ui.section title="Últimos 7 días" :level="2" id="sleep-week">
            @if ($weekSummary['nights'] > 0)
                <p class="md-body-medium mb-0">
                    Promedio {{ SleepLog::formatMinutes($weekSummary['average_minutes']) }}
                    · {{ $weekSummary['meeting_goal'] }} de {{ $weekSummary['nights'] }} {{ $weekSummary['nights'] === 1 ? 'registro cumplió' : 'registros cumplieron' }} tu meta
                    @if ($weekSummary['average_quality']) · Calidad promedio {{ str_replace('.', ',', $weekSummary['average_quality']) }}/5 @endif
                </p>
            @else
                <p class="md-body-medium mb-0">Sin registros en los últimos 7 días.</p>
            @endif
        </x-ui.section>
    @endif

    <x-ui.management-card id="sleep-history" title="Historial" icon="bi-clock-history"
                          :count="'('.$history->total().' / '.$totalLogs.')'"
                          sort-model="sort" :sort-options="$sorts" :sort-value="$sort"
                          :active-filters="count($activeFilters)" :paginator="$history" noun="registros"
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

        @if ($listState === DataState::CONTENT)
            <table class="md-table md-table--stack">
                <thead>
                    <tr>
                        <th scope="col">Fecha</th>
                        <th scope="col">Dormí</th>
                        <th scope="col">Desperté</th>
                        <th scope="col">Duración</th>
                        <th scope="col">Calidad</th>
                        <th scope="col" class="md-table__actions"><span class="visually-hidden">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($history as $log)
                        <tr wire:key="sleep-{{ $log->id }}">
                            <td class="md-table__date">{{ $log->woke_at->isToday() ? 'Hoy' : $log->woke_at->translatedFormat('j M Y') }}</td>
                            <td class="md-table__date">{{ $log->slept_at->format('H:i') }}</td>
                            <td class="md-table__date">{{ $log->woke_at->format('H:i') }}</td>
                            <td class="md-table__nowrap">{{ $log->durationText() }}</td>
                            <td>{{ $log->quality ? $log->quality.'/5 · '.SleepLog::QUALITIES[$log->quality] : '—' }}</td>
                            <td class="md-table__actions">
                                <x-ui.icon-action icon="bi-pencil" label="Editar el registro de sueño de {{ $log->durationText() }}" wire:click="edit('{{ $log->id }}')" />
                                <x-ui.destructive-action label="Eliminar el registro de sueño de {{ $log->durationText() }}" :iconOnly="true"
                                                         action="delete('{{ $log->id }}')"
                                                         title="Eliminar registro de sueño"
                                                         message="Se eliminará el registro de sueño del {{ $log->woke_at->translatedFormat('j M Y') }} ({{ $log->durationText() }})." />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @elseif ($listState === DataState::FILTERED_EMPTY)
            <x-ui.state variant="{{ DataState::FILTERED_EMPTY }}" title="Sin registros en este periodo"
                        message="Quita el filtro de periodo para ver otros registros." />
        @else
            <x-ui.state variant="{{ DataState::EMPTY }}" icon="bi-moon-stars" title="Aún no tienes registros de sueño"
                        message="Registra la primera noche con el botón +." />
        @endif
    </x-ui.management-card>

    @if ($showForm)
        @php
            $sleepErrors = $errors->hasAny(['slept_at', 'woke_at']);
            $sleepDetailErrors = $errors->hasAny(['quality', 'notes']);
        @endphp
        <div wire:key="sleep-form-{{ $editingId ?? 'new' }}">
            <x-ui.form-dialog :open="$showForm" close="closeForm" module="health" icon="bi-moon-stars"
                              title="{{ $editingId ? 'Editar sueño' : 'Registrar sueño' }}"
                              :submit="$editingId ? 'Actualizar sueño' : 'Guardar sueño'"
                              submit-action="save" id="sleep-form"
                              :sections="[
                                  'sleep' => ['label' => 'Sueño', 'icon' => 'bi-moon-stars', 'error' => $sleepErrors],
                                  'details' => ['label' => 'Detalles', 'icon' => 'bi-card-text', 'error' => $sleepDetailErrors],
                              ]">
                <x-ui.form-dialog-section name="sleep" title="Sueño" description="Cuándo te dormiste y cuándo despertaste.">
                    <div class="d-flex flex-column gap-3">
                        <div class="md-field-pair">
                            <x-ui.field name="slept_at" label="Me dormí" type="datetime-local" wire:model="slept_at" />
                            <x-ui.field name="woke_at" label="Desperté" type="datetime-local" wire:model="woke_at" />
                        </div>
                    </div>
                </x-ui.form-dialog-section>

                <x-ui.form-dialog-section name="details" title="Detalles" description="Cómo dormiste, notas y privacidad.">
                    <div class="d-flex flex-column gap-3">
                        <x-ui.choice-chips name="quality" label="¿Cómo dormiste?" :multiple="false" :options="$qualities" />
                        <x-ui.textarea name="notes" label="Notas" wire:model="notes" />
                        <x-ui.switch name="is_sensitive" label="Mantener oculto al asistente"
                                     help="Las consultas amplias del asistente no lo incluirán."
                                     :checked="$is_sensitive" wire:model="is_sensitive" />
                    </div>
                </x-ui.form-dialog-section>
            </x-ui.form-dialog>
        </div>
    @endif

    @if ($showGoal)
        <div wire:key="sleep-goal-form">
            <x-ui.form-dialog :open="$showGoal" close="closeGoal" module="health" icon="bi-bullseye"
                              title="Meta de horas de sueño" submit="Guardar meta"
                              submit-action="saveGoal" id="sleep-goal-form">
                <div class="d-flex flex-column gap-3">
                    <x-ui.field name="goalHours" label="Horas por noche" type="number" inputmode="decimal" step="0.5" min="4" max="14"
                                help="Entre 4 y 14 horas. Por defecto 8." wire:model="goalHours" />
                </div>
            </x-ui.form-dialog>
        </div>
    @endif
    <x-slot:rail>
        @include('livewire.health.partials.guardian-rail')
    </x-slot:rail>
</x-module-shell>
