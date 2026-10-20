@php
    use App\Support\Ui\DataState;

    $planState = DataState::resolve(visible: $plans->count(), total: $totalPlans);
    $planActive = $planStatus !== '' ? 1 : 0;
@endphp

<x-module-shell module="vehicles" :title="$vehicle->name" subtitle="Plan de mantenimiento recomendado" icon="bi-calendar2-check" :tabs="\App\Support\Ui\Tabs\VehicleTabs::for($vehicle, $energyUi)" :back="\App\Support\Ui\Tabs\VehicleTabs::back()">
    <x-slot:actions>
        <x-module-actions :primary="['label' => 'Activar plan', 'icon' => 'bi-plus-lg', 'action' => 'openPlanForm']" />
    </x-slot:actions>

    @if ($maintenanceMessage)
        <x-ui.snackbar>{{ $maintenanceMessage }}</x-ui.snackbar>
    @endif

    <x-ui.management-card clear="clearPlanFilters" id="vehicle-care-plans" title="Plan de mantenimiento" icon="bi-calendar2-check" :count="'('.$plans->count().' / '.$totalPlans.')'"
                          search="planSearch" search-placeholder="Buscar mantenimiento" :active-filters="$planActive" alpine="openMenu: null" class="vehicle-plans-section">
        <x-slot:filters>
            <div class="md-chip-rail md-mcard__filter-chips" role="group" aria-label="Filtros del plan" @click.outside="openMenu = null">
                <x-ui.filter-menu name="planStatus" label="Estado" allLabel="Todos los estados" :options="$statusOptions" :selected="$planStatus" />
            </div>
        </x-slot:filters>
        @php
            $appliedChips = array_values(array_filter([
                $planStatus !== '' ? ['key' => 'planStatus', 'value' => null, 'label' => 'Estado: '.($statusOptions[$planStatus] ?? $planStatus), 'icon' => 'bi-activity'] : null,
            ]));
        @endphp
        @if ($appliedChips !== [])
            <x-slot:strip>
                <x-ui.applied-filters :filters="$appliedChips" />
            </x-slot:strip>
        @endif
        @if ($planActive)
            <x-slot:filterActions>
                <x-ui.action variant="outlined" icon="bi-eraser" wire:click="clearPlanFilters" x-on:click="filtersOpen = false">Limpiar</x-ui.action>
                <x-ui.action variant="filled" icon="bi-funnel-fill" x-on:click="filtersOpen = false">Filtrar</x-ui.action>
            </x-slot:filterActions>
        @endif
        <x-slot:menu>
            <x-ui.menu-item icon="bi-clock-history" :href="route('vehicles.services', $vehicle)">Historial de servicios</x-ui.menu-item>
            <x-ui.menu-item icon="bi-journal-bookmark" :href="route('vehicles.catalog')">Catálogo de recomendaciones</x-ui.menu-item>
        </x-slot:menu>

        @if ($planState === DataState::CONTENT)
            <div class="vehicle-plan-grid">
                @foreach ($plans as $plan)
                    @php
                        $status = $plan->status_data['status'];
                    @endphp
                    <article class="md-card-outlined vehicle-plan-card is-{{ $status }}" wire:key="plan-{{ $plan->id }}">
                        <header>
                            <div><span>{{ $statusOptions[$status] ?? str_replace('_', ' ', $status) }}</span><h2>{{ $plan->template->name }}</h2></div>
                            <x-ui.destructive-action label="Desactivar {{ $plan->template->name }}" :iconOnly="true" size="sm"
                                                     action="deletePlan('{{ $plan->id }}')" title="Desactivar plan"
                                                     message="Se quita «{{ $plan->template->name }}» del plan y se elimina su historial de servicios." />
                        </header>
                        <p>{{ $plan->interval_days ? 'Cada '.$plan->interval_days.' días' : '' }}{{ $plan->interval_days && $plan->interval_usage ? ' · ' : '' }}{{ $plan->interval_usage ? 'Cada '.number_format($plan->interval_usage, 0, ',', '.').' '.$vehicle->usage_unit : '' }}</p>
                        @if ($plan->status_data['next_due_date'])
                            <small>Próximo por {{ $plan->status_data['next_due_reason'] }}: {{ $plan->status_data['next_due_date']->translatedFormat('d M Y') }}</small>
                        @endif
                        <small>{{ $plan->latestMaintenanceLog ? 'Último servicio: '.$plan->latestMaintenanceLog->performed_on->translatedFormat('d M Y') : 'Sin servicios registrados' }}</small>
                        <x-ui.action variant="tonal" size="sm" icon="bi-wrench-adjustable" class="mt-3" wire:click="openMaintenanceForm('{{ $plan->id }}')">Registrar servicio</x-ui.action>
                    </article>
                @endforeach
            </div>
        @elseif ($planState === DataState::FILTERED_EMPTY)
            <x-ui.state variant="filtered-empty" icon="bi-calendar2-check" message="Hay mantenimientos en el plan, pero ninguno coincide con la búsqueda o el estado.">
                <x-slot:actions>
                    <x-ui.action variant="outlined" icon="bi-x-circle" wire:click="clearPlanFilters">Limpiar filtros</x-ui.action>
                </x-slot:actions>
            </x-ui.state>
        @else
            <x-ui.state variant="empty" icon="bi-calendar2-check" title="No hay mantenimientos programados"
                        message="Activa las recomendaciones del catálogo que apliquen a este vehículo con «Activar plan»." />
        @endif
    </x-ui.management-card>

    @include('livewire.vehicle.partials.maintenance-forms')
</x-module-shell>
