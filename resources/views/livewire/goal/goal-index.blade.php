@php
    use App\Support\Ui\DataState;

    $goalsState = DataState::resolve(
        visible: $goals->count(),
        total: $statusFilter === 'all' ? $goals->count() : 1,
    );

    $statusLabels = ['active' => 'Activos', 'completed' => 'Completados', 'abandoned' => 'Abandonados', 'all' => 'Todos'];
@endphp

<x-module-shell module="goals">
    <x-slot:actions>
        <x-module-actions :primary="['label' => 'Agregar objetivo', 'icon' => 'bi-flag', 'action' => 'openForm']" />
    </x-slot:actions>

    <x-ui.metric-grid label="Resumen de objetivos">
        <x-ui.metric label="Activos" icon="bi-flag-fill" tone="primary" :value="$activeCount" />
        <x-ui.metric label="Completados" icon="bi-check-circle-fill" tone="success" :value="$completedCount" />
    </x-ui.metric-grid>

    <x-ui.management-card id="goals" title="Objetivos" icon="bi-flag" :count="'('.$goals->total().')'"
                          :active-filters="$statusFilter !== 'active' ? 1 : 0" :paginator="$goals" noun="objetivos"
                          sort-model="sort" :sort-options="$sorts" :sort-value="$sort">
        <x-slot:filters>
            <div class="md-chip-rail md-mcard__filter-chips" role="group" aria-label="Estado de los objetivos">
                @foreach ($statusLabels as $value => $label)
                    <x-ui.chip variant="filter" :selected="$statusFilter === $value" wire:click="$set('statusFilter', '{{ $value }}')">{{ $label }}</x-ui.chip>
                @endforeach
            </div>
        </x-slot:filters>
        @php
            $appliedChips = array_values(array_filter([
                $statusFilter !== 'all' ? ['key' => 'statusFilter', 'value' => null, 'label' => 'Estado: '.($statusLabels[$statusFilter] ?? $statusFilter), 'icon' => 'bi-flag'] : null,
            ]));
        @endphp
        @if ($appliedChips !== [])
            <x-slot:strip>
                <x-ui.applied-filters :filters="$appliedChips" />
            </x-slot:strip>
        @endif
        @if ($goalsState === DataState::CONTENT)
            <x-ui.list label="Objetivos">
                @foreach ($goals as $goal)
                    <x-ui.list-item :headline="$goal->title" :supporting="$goal->description" wire:key="goal-{{ $goal->id }}">
                        <x-slot:leading>
                            @if ($goal->status === 'active')
                                <x-ui.icon name="bi-flag-fill" tone="primary" label="Objetivo activo" />
                            @elseif ($goal->status === 'completed')
                                <x-ui.icon name="bi-check-circle-fill" tone="success" label="Objetivo completado" />
                            @else
                                <x-ui.icon name="bi-x-circle-fill" tone="muted" label="Objetivo abandonado" />
                            @endif
                        </x-slot:leading>

                        <div class="goal-card__meta">
                            @if ($goal->start_date)
                                <x-ui.chip variant="tonal" icon="bi-calendar">{{ $goal->start_date->format('d M Y') }}</x-ui.chip>
                            @endif
                            @if ($goal->due_date)
                                <x-ui.chip variant="tonal" icon="bi-calendar-event"
                                           :tone="$goal->due_date->isPast() && $goal->status === 'active' ? 'danger' : 'neutral'">
                                    {{ $goal->due_date->format('d M Y') }}
                                </x-ui.chip>
                            @endif
                        </div>

                        <x-slot:trailing>
                            <x-ui.row-actions :label="'Más acciones de '.$goal->title">
                                <x-slot:primary wire:click="openForm('{{ $goal->id }}')">Editar</x-slot:primary>
                                @if ($goal->status === 'active')
                                    <x-ui.menu-item icon="bi-check-circle" wire:click="updateStatus('{{ $goal->id }}', 'completed')">Completar</x-ui.menu-item>
                                    <x-ui.menu-item icon="bi-x-circle" wire:click="updateStatus('{{ $goal->id }}', 'abandoned')">Abandonar</x-ui.menu-item>
                                @else
                                    <x-ui.menu-item icon="bi-arrow-counterclockwise" wire:click="updateStatus('{{ $goal->id }}', 'active')">Reactivar</x-ui.menu-item>
                                @endif
                                <x-ui.menu-divider />
                                <x-ui.menu-item icon="bi-trash" tone="danger" wire:click="delete('{{ $goal->id }}')"
                                                wire:confirm="¿Eliminar este objetivo?">Eliminar</x-ui.menu-item>
                            </x-ui.row-actions>
                        </x-slot:trailing>
                    </x-ui.list-item>
                @endforeach
            </x-ui.list>
        @elseif ($goalsState === DataState::FILTERED_EMPTY)
            <x-ui.state variant="filtered-empty" icon="bi-flag"
                        message="No hay objetivos con el estado «{{ $statusLabels[$statusFilter] ?? $statusFilter }}».">
                <x-slot:actions>
                    <x-ui.action variant="outlined" icon="bi-x-circle" wire:click="$set('statusFilter', 'all')">Ver todos</x-ui.action>
                </x-slot:actions>
            </x-ui.state>
        @else
            <x-ui.state variant="empty" icon="bi-flag" title="Sin objetivos todavía"
                        message="Define un objetivo para seguir su avance y sus hitos." />
        @endif
    </x-ui.management-card>

    <x-ui.form-dialog :open="$showForm" close="closeForm" submit-action="save" id="goal-dialog"
                      :title="$editingId ? 'Editar objetivo' : 'Agregar objetivo'" icon="bi-flag"
                      :submit="$editingId ? 'Actualizar' : 'Guardar'"
                      :sections="[
                          'basic' => ['label' => 'Información básica', 'icon' => 'bi-flag', 'error' => $errors->hasAny(['title', 'startDate', 'dueDate'])],
                          'kpi' => ['label' => 'Indicador (KPI)', 'icon' => 'bi-graph-up-arrow', 'error' => $errors->hasAny(['kpiName', 'kpiUnit', 'kpiStartValue', 'kpiTargetValue'])],
                      ]">
        <x-ui.form-dialog-section name="basic" title="Información básica">
            <div class="d-flex flex-column gap-3">
                <x-ui.field name="title" label="Título" :required="true" wire:model="title" />
                <x-ui.textarea name="description" label="Descripción" rows="3" wire:model="description" />
                <x-ui.select name="formStatus" label="Estado" :selected="$formStatus"
                             :options="['active' => 'Activo', 'completed' => 'Completado', 'abandoned' => 'Abandonado']"
                             wire:model="formStatus" />
                <div class="md-field-pair">
                    <x-ui.field name="startDate" label="Fecha inicio" type="date" wire:model="startDate" />
                    <x-ui.field name="dueDate" label="Fecha límite" type="date" wire:model="dueDate" />
                </div>
            </div>
        </x-ui.form-dialog-section>

        <x-ui.form-dialog-section name="kpi" title="Indicador (KPI)" description="Opcional: compara el avance real con el ritmo esperado.">
            <div class="d-flex flex-column gap-3">
                <label class="goal-kpi-toggle">
                    <input wire:model.live="kpiEnabled" type="checkbox">
                    <span><i class="bi bi-graph-up-arrow" aria-hidden="true"></i> Configurar KPI único</span>
                </label>

                @if ($kpiEnabled)
                    <div class="goal-kpi-form">
                        <x-ui.field name="kpiName" label="Nombre del KPI" wire:model="kpiName" />
                        <x-ui.field name="kpiUnit" label="Unidad" wire:model="kpiUnit" />
                        <x-ui.select name="kpiDirection" label="Dirección" :selected="$kpiDirection"
                                     :options="['increase' => 'Aumentar hasta la meta', 'decrease' => 'Reducir hasta la meta']"
                                     wire:model="kpiDirection" />
                        <div class="md-field-pair">
                            <x-ui.field name="kpiStartValue" label="Valor inicial" type="number" step="0.01" wire:model="kpiStartValue" />
                            <x-ui.field name="kpiTargetValue" label="Valor objetivo" type="number" step="0.01" wire:model="kpiTargetValue" />
                        </div>
                        <p class="goal-kpi-form__hint">Con fechas de inicio y límite, el detalle comparará el avance real con el ritmo esperado.</p>
                    </div>
                @endif
            </div>
        </x-ui.form-dialog-section>
    </x-ui.form-dialog>
</x-module-shell>
