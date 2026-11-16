@php
    use App\Support\Ui\DataState;

    $listState = $measurements->count() > 0
        ? DataState::CONTENT
        : ($totalMeasurements > 0 && count($activeFilters) > 0 ? DataState::FILTERED_EMPTY : DataState::EMPTY);
@endphp

<x-module-shell module="health">
    <x-slot:actions>
        <x-module-actions :primary="['label' => 'Registrar', 'icon' => 'bi-plus-lg', 'action' => 'openForm']" />
    </x-slot:actions>

    <p class="md-body-medium" id="health-measurements-notice">
        MiSo registra tus mediciones y muestra su evolución. No es un dispositivo médico ni reemplaza a tu profesional de salud.
    </p>

    @if ($latestByType->isNotEmpty())
        <x-ui.section title="Última medición" :level="2" id="health-latest-measurement">
            @foreach ($latestByType as $latest)
                <div wire:key="latest-{{ $latest->type }}">
                    <p class="md-title-large mb-0">
                        <i class="bi {{ \App\Models\HealthMeasurement::TYPES[$latest->type]['icon'] ?? 'bi-heart-pulse' }}" aria-hidden="true"></i>
                        {{ $latest->typeLabel() }}
                        <strong>{{ $latest->displayValue() }} {{ $latest->unit }}</strong>
                    </p>
                    <p class="md-body-medium mb-0">
                        {{ $latest->measured_at->isToday() ? 'Hoy' : $latest->measured_at->translatedFormat('j M Y') }} · {{ $latest->measured_at->format('H:i') }}
                        @if ($latest->contextLabel()) · {{ $latest->contextLabel() }} @endif
                        @if ($latest->pulse) · Pulso {{ $latest->pulse }} lpm @endif
                    </p>
                    @if ($latest->isOutOfRange())
                        <p class="md-body-medium mb-0" role="status">
                            <i class="bi bi-info-circle" aria-hidden="true"></i>
                            Fuera del rango de referencia general ({{ $latest->referenceText() }}). Si te preocupa, consúltalo con tu profesional de salud.
                        </p>
                    @endif
                </div>
            @endforeach
        </x-ui.section>
    @endif

    <x-ui.management-card id="health-measurements-history" title="Historial" icon="bi-clock-history"
                          :count="'('.$measurements->total().' / '.$totalMeasurements.')'"
                          sort-model="sort" :sort-options="$sorts" :sort-value="$sort"
                          :active-filters="count($activeFilters)" :paginator="$measurements" noun="mediciones"
                          alpine="draftPeriod: '', draftType: ''"
                          on-filters-open="draftPeriod = $wire.period; draftType = $wire.typeFilter">
        <x-slot:filters>
            <x-ui.select name="filterPeriod" label="Periodo" :options="$periods" :selected="$period" icon="bi-calendar-event" x-model="draftPeriod" />
            <x-ui.select name="filterType" label="Tipo" placeholder="Todos" :options="$typeOptions" :selected="$typeFilter" icon="bi-heart-pulse" x-model="draftType" />
        </x-slot:filters>
        <x-slot:filterActions>
            <x-ui.action variant="outlined" icon="bi-eraser" wire:click="clearFilters" x-on:click="filtersOpen = false">Limpiar</x-ui.action>
            <x-ui.action variant="filled" icon="bi-funnel-fill" x-on:click="$wire.applyFilters(draftPeriod, draftType); filtersOpen = false">Filtrar</x-ui.action>
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
                        <th scope="col">Hora</th>
                        <th scope="col">Medición</th>
                        <th scope="col">Valor</th>
                        <th scope="col">Estado</th>
                        <th scope="col" class="md-table__actions"><span class="visually-hidden">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($measurements as $measurement)
                        <tr wire:key="measurement-{{ $measurement->id }}">
                            <td class="md-table__date">{{ $measurement->measured_at->isToday() ? 'Hoy' : $measurement->measured_at->translatedFormat('j M Y') }}</td>
                            <td class="md-table__date">{{ $measurement->measured_at->format('H:i') }}</td>
                            <td class="md-table__title">{{ $measurement->typeLabel() }}@if ($measurement->contextLabel()) <span class="md-body-small">· {{ $measurement->contextLabel() }}</span>@endif
                                @if ($measurement->healthEvent) <span class="md-body-small">· {{ $measurement->healthEvent->title }}</span>@endif</td>
                            <td class="md-table__nowrap">
                                {{ $measurement->displayValue() }} {{ $measurement->unit }}
                                @if ($measurement->pulse) <span class="md-body-small">· {{ $measurement->pulse }} lpm</span> @endif
                            </td>
                            <td>
                                @if ($measurement->isOutOfRange())
                                    <x-ui.badge tone="warning"><i class="bi bi-info-circle" aria-hidden="true"></i> Fuera de rango</x-ui.badge>
                                @else
                                    <x-ui.badge tone="neutral">En rango</x-ui.badge>
                                @endif
                            </td>
                            <td class="md-table__actions">
                                <x-ui.icon-action icon="bi-pencil" label="Editar la medición de {{ $measurement->displayValue() }} {{ $measurement->unit }}"
                                                  wire:click="edit('{{ $measurement->id }}')" />
                                <x-ui.destructive-action label="Eliminar la medición de {{ $measurement->displayValue() }} {{ $measurement->unit }}" :iconOnly="true"
                                                         action="delete('{{ $measurement->id }}')"
                                                         title="Eliminar medición"
                                                         message="Se eliminará la medición de {{ $measurement->displayValue() }} {{ $measurement->unit }} del {{ $measurement->measured_at->translatedFormat('j M Y') }}." />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @elseif ($listState === DataState::FILTERED_EMPTY)
            <x-ui.state variant="{{ DataState::FILTERED_EMPTY }}" title="Sin mediciones con estos filtros"
                        message="Quita un filtro para ver otras mediciones." />
        @else
            <x-ui.state variant="{{ DataState::EMPTY }}" icon="bi-heart-pulse" title="Aún no tienes mediciones"
                        message="Registra la primera con el botón +." />
        @endif
    </x-ui.management-card>

    @if ($showForm)
        <div wire:key="health-measurement-form-{{ $editingId ?? 'new' }}">
            <x-ui.form-dialog :open="$showForm" close="closeForm"
                              title="{{ $editingId ? 'Editar medición' : 'Registrar medición' }}"
                              submit-action="save" id="health-measurement-form">
                <x-ui.select name="type" label="Tipo" :options="$typeOptions" :selected="$type" wire:model.live="type" />
                <x-ui.field name="value" :label="$definition['value_label'].' ('.$definition['unit'].')'" type="number" inputmode="decimal"
                            step="any" wire:model="value" />
                @isset($definition['value2_label'])
                    <x-ui.field name="value2" :label="$definition['value2_label'].' ('.$definition['unit'].')'" type="number" inputmode="decimal"
                                step="any" wire:model="value2" />
                @endisset
                @if ($contextOptions !== [])
                    <x-ui.select name="context" label="Momento" placeholder="Sin indicar" :options="$contextOptions" :selected="$context" wire:model="context" />
                @endif
                <x-ui.field name="measuredAt" label="Fecha y hora" type="datetime-local" wire:model="measuredAt" />

                <details>
                    <summary>Más detalle</summary>
                    @isset($definition['limits']['pulse'])
                        <x-ui.field name="pulse" label="Pulso (lpm)" type="number" inputmode="numeric" step="1" wire:model="pulse" />
                    @endisset
                    <x-ui.select name="health_event_id" label="Examen relacionado" placeholder="Ninguno"
                                 help="Un «Chequeo o examen» de Salud al que pertenece esta medición."
                                 :options="$examOptions" :selected="$health_event_id" wire:model="health_event_id" />
                    <x-ui.textarea name="notes" label="Notas" wire:model="notes" />
                    <x-ui.switch name="isSensitive" label="Mantener oculta al asistente"
                                 help="Las consultas amplias del asistente no la incluirán."
                                 :checked="$isSensitive" wire:model="isSensitive" />
                </details>
            </x-ui.form-dialog>
        </div>
    @endif
</x-module-shell>
