@php
    use App\Support\Ui\DataState;

    $serviceActive = count(array_filter([$servicePeriod, $servicePlan], static fn ($value) => $value !== ''));
    $serviceState = DataState::resolve(visible: $maintenanceLogs->count(), total: $totalCount);
    $fabAction = $planOptions === []
        ? ['label' => 'Activar un plan', 'icon' => 'bi-calendar2-check', 'href' => route('vehicles.maintenance', $vehicle)]
        : ['label' => 'Registrar servicio', 'icon' => 'bi-plus-lg', 'action' => 'openMaintenanceForm'];
@endphp

<x-module-shell module="vehicles" :title="$vehicle->name" subtitle="Historial de servicios realizados" icon="bi-clock-history" :tabs="\App\Support\Ui\Tabs\VehicleTabs::for($vehicle, $energyUi)" :back="\App\Support\Ui\Tabs\VehicleTabs::back()">
    <x-slot:actions>
        <x-module-actions :primary="$fabAction" />
    </x-slot:actions>

    @if ($maintenanceMessage)
        <x-ui.snackbar>{{ $maintenanceMessage }}</x-ui.snackbar>
    @endif

    <section class="vehicle-metrics-grid">
        <article class="md-card-outlined"><span>Servicios</span><strong>{{ number_format($maintenanceLogs->total(), 0, ',', '.') }}</strong><small>{{ $periodOptions[$servicePeriod] ?? 'Todo el historial' }}</small></article>
        <article class="md-card-outlined"><span>Gastado en servicios</span><strong>$ {{ number_format($filteredCost, 0, ',', '.') }}</strong><small>{{ $periodOptions[$servicePeriod] ?? 'Todo el historial' }}</small></article>
        <article class="md-card-outlined"><span>Último servicio</span><strong>{{ $latestService ? \Illuminate\Support\Carbon::parse($latestService)->translatedFormat('d M Y') : '—' }}</strong><small>{{ $latestService ? \Illuminate\Support\Carbon::parse($latestService)->diffForHumans() : 'Sin servicios registrados' }}</small></article>
    </section>

    <x-ui.management-card clear="clearServiceFilters" id="vehicle-maintenance-history" title="Historial de servicios" icon="bi-clock-history"
                          :count="'('.$maintenanceLogs->total().' / '.$totalCount.')'" search="serviceSearch" search-placeholder="Buscar servicio, taller o nota"
                          :active-filters="$serviceActive" :paginator="$maintenanceLogs" sort-model="serviceSort" :sort-options="$sorts" :sort-value="$serviceSort" noun="servicios" alpine="openMenu: null" class="vehicle-history-section" flush>
        <x-slot:filters>
            <div class="md-chip-rail md-mcard__filter-chips" role="group" aria-label="Filtros del historial de servicios" @click.outside="openMenu = null">
                <x-ui.filter-menu name="servicePeriod" label="Periodo" allLabel="Todo el historial" :options="$periodOptions" :selected="$servicePeriod" />
                @if ($planOptions !== [])
                    <x-ui.filter-menu name="servicePlan" label="Mantenimiento" allLabel="Todos los mantenimientos" :options="$planOptions" :selected="$servicePlan" />
                @endif
            </div>
        </x-slot:filters>
        @php
            $appliedChips = array_values(array_filter([
                $servicePeriod !== '' ? ['key' => 'servicePeriod', 'value' => null, 'label' => 'Periodo: '.($periodOptions[$servicePeriod] ?? $servicePeriod), 'icon' => 'bi-calendar-range'] : null,
                $servicePlan !== '' ? ['key' => 'servicePlan', 'value' => null, 'label' => 'Mantenimiento: '.($planOptions[$servicePlan] ?? $servicePlan), 'icon' => 'bi-wrench'] : null,
            ]));
        @endphp
        @if ($appliedChips !== [])
            <x-slot:strip>
                <x-ui.applied-filters :filters="$appliedChips" />
            </x-slot:strip>
        @endif
        @if ($serviceActive)
            <x-slot:filterActions>
                <x-ui.action variant="outlined" icon="bi-eraser" wire:click="clearServiceFilters" x-on:click="filtersOpen = false">Limpiar</x-ui.action>
                <x-ui.action variant="filled" icon="bi-funnel-fill" x-on:click="filtersOpen = false">Filtrar</x-ui.action>
            </x-slot:filterActions>
        @endif

        @if ($serviceState === DataState::CONTENT)
            <div class="vehicle-history-table"><div class="table-responsive"><table class="table md-table mb-0 align-middle"><thead><tr><th>Fecha</th><th>Servicio</th><th>Lectura</th><th>Costo</th><th><span class="visually-hidden">Acciones</span></th></tr></thead><tbody>
                @foreach ($maintenanceLogs as $log)
                    <tr wire:key="maintenance-row-{{ $log->id }}">
                        <td>{{ $log->performed_on->format('d/m/Y') }}</td>
                        <td><strong>{{ $log->plan->template->name }}</strong>@if ($log->provider || $log->notes)<br><small>{{ collect([$log->provider, $log->notes])->filter()->implode(' · ') }}</small>@endif</td>
                        <td>{{ $log->usage_reading !== null ? number_format($log->usage_reading, 0, ',', '.').' '.$vehicle->usage_unit : '—' }}</td>
                        <td>{{ $log->cost !== null ? '$ '.number_format($log->cost, 0, ',', '.') : '—' }}</td>
                        <td><div class="d-flex">
                            <x-ui.row-actions :label="'Más acciones de '.$log->plan->template->name.' del '.$log->performed_on->format('d/m/Y')">
                                <x-slot:primary wire:click="editMaintenanceLog('{{ $log->id }}')">Editar</x-slot:primary>
                                <x-ui.menu-divider />
                                <x-ui.menu-item icon="bi-trash" tone="danger" wire:click="deleteMaintenanceLog('{{ $log->id }}')"
                                                wire:confirm="El servicio se elimina del historial y el plan recalcula su próximo cuidado.">Eliminar</x-ui.menu-item>
                            </x-ui.row-actions>
                        </div></td>
                    </tr>
                @endforeach
            </tbody></table></div></div>

            <div class="vehicle-history-cards">
                @foreach ($maintenanceLogs as $log)
                    <article class="md-card-outlined" wire:key="maintenance-card-{{ $log->id }}">
                        <header><time>{{ $log->performed_on->translatedFormat('d M Y') }}</time><div>
                            <x-ui.row-actions :label="'Más acciones de '.$log->plan->template->name.' del '.$log->performed_on->format('d/m/Y')">
                                <x-slot:primary wire:click="editMaintenanceLog('{{ $log->id }}')">Editar</x-slot:primary>
                                <x-ui.menu-divider />
                                <x-ui.menu-item icon="bi-trash" tone="danger" wire:click="deleteMaintenanceLog('{{ $log->id }}')"
                                                wire:confirm="El servicio se elimina del historial y el plan recalcula su próximo cuidado.">Eliminar</x-ui.menu-item>
                            </x-ui.row-actions>
                        </div></header>
                        <strong>{{ $log->plan->template->name }}</strong>
                        <span>{{ $log->provider ?: 'Sin proveedor' }}</span>
                        <dl><div><dt>Lectura</dt><dd>{{ $log->usage_reading !== null ? number_format($log->usage_reading, 0, ',', '.').' '.$vehicle->usage_unit : '—' }}</dd></div><div><dt>Costo</dt><dd>{{ $log->cost !== null ? '$ '.number_format($log->cost, 0, ',', '.') : '—' }}</dd></div></dl>
                    </article>
                @endforeach
            </div>
        @elseif ($serviceState === DataState::FILTERED_EMPTY)
            <x-ui.state variant="filtered-empty" icon="bi-clock-history" message="Hay servicios registrados, pero ninguno coincide con la búsqueda o los filtros activos.">
                <x-slot:actions>
                    <x-ui.action variant="outlined" icon="bi-x-circle" wire:click="clearServiceFilters">Limpiar filtros</x-ui.action>
                </x-slot:actions>
            </x-ui.state>
        @elseif ($planOptions === [])
            <x-ui.state variant="empty" icon="bi-clock-history" title="Sin servicios registrados todavía"
                        message="Primero activa los mantenimientos del vehículo en «Plan de mantenimiento»; luego registra aquí lo que se le haga." />
        @else
            <x-ui.state variant="empty" icon="bi-clock-history" title="Sin servicios registrados todavía"
                        message="Registra el primer servicio con el botón de acción." />
        @endif
    </x-ui.management-card>

    @include('livewire.vehicle.partials.maintenance-forms')
</x-module-shell>
