@php
    use App\Support\Ui\DataState;

    $fuelActive = count(array_filter([$fuelPeriod, $fuelFill, $fuelSource], static fn ($value) => $value !== ''));
    $fuelState = DataState::resolve(visible: $energyLogs->count(), total: $totalCount);
@endphp

<x-module-shell module="vehicles" :title="$vehicle->name" :subtitle="$energyUi['heading']" icon="bi-fuel-pump" :tabs="\App\Support\Ui\Tabs\VehicleTabs::for($vehicle, $energyUi)" :back="\App\Support\Ui\Tabs\VehicleTabs::back()">
    <x-slot:actions>
        <x-module-actions
            :primary="['label' => $energyUi['action'], 'icon' => 'bi-plus-lg', 'action' => 'openEnergyForm']" />
    </x-slot:actions>

    <section class="vehicle-metrics-grid">
        <article class="md-card-outlined"><span>Último precio</span><strong>{{ $energyAnalytics['latest_price'] !== null ? '$ '.number_format($energyAnalytics['latest_price'], 0, ',', '.') : '—' }}</strong><small>por {{ $energyAnalytics['unit'] }}</small></article>
        <article class="md-card-outlined"><span>Promedio ponderado</span><strong>{{ $energyAnalytics['weighted_average_price'] !== null ? '$ '.number_format($energyAnalytics['weighted_average_price'], 0, ',', '.') : '—' }}</strong><small>por {{ $energyAnalytics['unit'] }}</small></article>
        <article class="md-card-outlined"><span>Rendimiento</span><strong>{{ $energyAnalytics['latest_efficiency'] ? number_format($energyAnalytics['latest_efficiency']['efficiency'], 1, ',', '.') : '—' }}</strong><small>{{ $energyAnalytics['latest_efficiency'] ? $vehicle->usage_unit.'/'.$energyAnalytics['unit'] : 'Requiere dos llenados completos' }}</small></article>
    </section>

    <x-ui.management-card clear="clearFuelFilters" id="vehicle-energy-history" :title="'Historial de '.strtolower($energyUi['tab'])" icon="bi-fuel-pump"
                          :count="'('.$energyLogs->total().' / '.$totalCount.')'" search="fuelSearch" search-placeholder="Buscar estación o nota"
                          :active-filters="$fuelActive" :paginator="$energyLogs" sort-model="fuelSort" :sort-options="$sorts" :sort-value="$fuelSort" noun="registros" alpine="openMenu: null" class="vehicle-history-section" flush>
        <x-slot:filters>
            <div class="md-chip-rail md-mcard__filter-chips" role="group" aria-label="Filtros del historial" @click.outside="openMenu = null">
                <x-ui.filter-menu name="fuelPeriod" label="Periodo" allLabel="Todo el historial" :options="$periodOptions" :selected="$fuelPeriod" />
                <x-ui.filter-menu name="fuelFill" label="Tipo de carga" allLabel="Completas y parciales"
                                  :options="['full' => 'Solo completas', 'partial' => 'Solo parciales']" :selected="$fuelFill" />
                @if (count($energySources) > 1)
                    <x-ui.filter-menu name="fuelSource" label="Fuente" allLabel="Todas las fuentes"
                                      :options="collect($energySources)->mapWithKeys(fn ($source) => [$source => $source === 'electrico' ? 'Electricidad' : ucfirst($source)])->all()"
                                      :selected="$fuelSource" />
                @endif
            </div>
        </x-slot:filters>
        @php
            $appliedChips = array_values(array_filter([
                $fuelPeriod !== '' ? ['key' => 'fuelPeriod', 'value' => null, 'label' => 'Periodo: '.($periodOptions[$fuelPeriod] ?? $fuelPeriod), 'icon' => 'bi-calendar-range'] : null,
                $fuelFill !== '' ? ['key' => 'fuelFill', 'value' => null, 'label' => 'Carga: '.($fuelFill === 'full' ? 'Solo completas' : 'Solo parciales'), 'icon' => 'bi-fuel-pump'] : null,
                $fuelSource !== '' ? ['key' => 'fuelSource', 'value' => null, 'label' => 'Fuente: '.($fuelSource === 'electrico' ? 'Electricidad' : ucfirst($fuelSource)), 'icon' => 'bi-lightning-charge'] : null,
            ]));
        @endphp
        @if ($appliedChips !== [])
            <x-slot:strip>
                <x-ui.applied-filters :filters="$appliedChips" />
            </x-slot:strip>
        @endif
        @if ($fuelActive)
            <x-slot:filterActions>
                <x-ui.action variant="outlined" icon="bi-eraser" wire:click="clearFuelFilters" x-on:click="filtersOpen = false">Limpiar</x-ui.action>
                <x-ui.action variant="filled" icon="bi-funnel-fill" x-on:click="filtersOpen = false">Filtrar</x-ui.action>
            </x-slot:filterActions>
        @endif

        @if ($fuelState === DataState::CONTENT)
            <div class="vehicle-history-table"><div class="table-responsive"><table class="table md-table mb-0 align-middle"><thead><tr><th>Fecha</th><th>Detalle</th><th>Lectura</th><th>Precio unitario</th><th>Costo</th><th><span class="visually-hidden">Acciones</span></th></tr></thead><tbody>
                @foreach ($energyLogs as $log)
                    <tr wire:key="energy-row-{{ $log->id }}">
                        <td>{{ $log->recorded_on->format('d/m/Y') }}</td>
                        <td><strong>{{ number_format($log->display_quantity, 2, ',', '.') }} {{ $log->display_unit }}</strong><br><small class="text-capitalize">{{ $log->energy_source }} · {{ $log->is_full ? ($log->energy_source === 'electrico' ? 'Carga completa' : 'Tanque lleno') : 'Parcial' }}{{ $log->provider ? ' · '.$log->provider : '' }}</small></td>
                        <td>{{ $log->usage_reading !== null ? number_format($log->usage_reading, 0, ',', '.').' '.$vehicle->usage_unit : '—' }}</td>
                        <td>{{ $log->unit_price !== null ? '$ '.number_format($log->unit_price, 0, ',', '.').' / '.$log->display_unit : '—' }}</td>
                        <td>{{ $log->cost !== null ? '$ '.number_format($log->cost, 0, ',', '.') : '—' }}</td>
                        <td><div class="d-flex">
                            <x-ui.row-actions :label="'Más acciones del registro del '.$log->recorded_on->format('d/m/Y')">
                                <x-slot:primary wire:click="openEnergyForm('{{ $log->id }}')">Editar</x-slot:primary>
                                <x-ui.menu-divider />
                                <x-ui.menu-item icon="bi-trash" tone="danger" wire:click="deleteEnergyLog('{{ $log->id }}')"
                                                wire:confirm="El registro se elimina y la lectura actual se recalcula.">Eliminar</x-ui.menu-item>
                            </x-ui.row-actions>
                        </div></td>
                    </tr>
                @endforeach
            </tbody></table></div></div>

            <div class="vehicle-history-cards">
                @foreach ($energyLogs as $log)
                    <article class="md-card-outlined" wire:key="energy-card-{{ $log->id }}">
                        <header><time>{{ $log->recorded_on->translatedFormat('d M Y') }}</time><div>
                            <x-ui.row-actions :label="'Más acciones del registro del '.$log->recorded_on->format('d/m/Y')">
                                <x-slot:primary wire:click="openEnergyForm('{{ $log->id }}')">Editar</x-slot:primary>
                                <x-ui.menu-divider />
                                <x-ui.menu-item icon="bi-trash" tone="danger" wire:click="deleteEnergyLog('{{ $log->id }}')"
                                                wire:confirm="El registro se elimina y la lectura actual se recalcula.">Eliminar</x-ui.menu-item>
                            </x-ui.row-actions>
                        </div></header>
                        <strong>{{ number_format($log->display_quantity, 2, ',', '.') }} {{ $log->display_unit }}</strong>
                        <span class="text-capitalize">{{ $log->energy_source }} · {{ $log->is_full ? 'Completo' : 'Parcial' }}{{ $log->provider ? ' · '.$log->provider : '' }}</span>
                        <dl><div><dt>Lectura</dt><dd>{{ $log->usage_reading !== null ? number_format($log->usage_reading, 0, ',', '.').' '.$vehicle->usage_unit : '—' }}</dd></div><div><dt>Precio</dt><dd>{{ $log->unit_price !== null ? '$ '.number_format($log->unit_price, 0, ',', '.') : '—' }}</dd></div><div><dt>Total</dt><dd>{{ $log->cost !== null ? '$ '.number_format($log->cost, 0, ',', '.') : '—' }}</dd></div></dl>
                    </article>
                @endforeach
            </div>
        @elseif ($fuelState === DataState::FILTERED_EMPTY)
            <x-ui.state variant="filtered-empty" icon="bi-fuel-pump" message="Hay registros, pero ninguno coincide con la búsqueda o los filtros activos.">
                <x-slot:actions>
                    <x-ui.action variant="outlined" icon="bi-x-circle" wire:click="clearFuelFilters">Limpiar filtros</x-ui.action>
                </x-slot:actions>
            </x-ui.state>
        @else
            <x-ui.state variant="empty" icon="bi-fuel-pump" :title="$energyUi['empty']" message="Registra el primero con el botón de acción." />
        @endif
    </x-ui.management-card>

    @include('livewire.vehicle.partials.energy-form')
</x-module-shell>
