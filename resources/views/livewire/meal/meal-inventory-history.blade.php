<x-module-shell module="meals" title="Kardex del ingrediente" :back="['href' => $backUrl, 'label' => 'Volver']">
    @if ($product)
    <x-ui.section :title="'Kardex · '.$product['name']" :level="2">
        <x-ui.card variant="outlined">
            <p class="md-title-medium">Stock actual: {{ $product['stock'] === null ? 'Producto histórico' : $product['stock'].' '.$product['unit'] }}</p>
            <p class="md-body-small">{{ $coverage_started_at ? 'Cobertura completa desde '.\Carbon\Carbon::parse($coverage_started_at)->format('d/m/Y H:i:s') : 'Todavía no hay saldo de apertura registrado.' }}</p>
            @if ($product['successor'])<p class="md-body-small">Fusionado en {{ $product['successor']['name'] }}. Su saldo se consulta por separado.</p><x-ui.action variant="text" :href="route('meals.inventory.history', ['item' => $product['successor']['id']])" wire:navigate>Consultar ingrediente actual</x-ui.action>
@endif
            @foreach ($product['predecessors'] as $previous)<x-ui.action variant="text" :href="route('meals.inventory.history', ['item' => $previous['id']])" wire:navigate>Historia de {{ $previous['name'] }}</x-ui.action>
@endforeach
        </x-ui.card>
    </x-ui.section>
    @endif
    <x-ui.management-card id="inventory-history" title="Movimientos del ingrediente" icon="bi-journal-text" :paginator="$movementsPage" noun="movimientos" :active-filters="count($this->activeFilters())">
        <x-slot:filters>
            <div class="d-flex flex-column gap-3">
                <x-ui.field name="from" label="Desde" type="date" wire:model.live="from" />
                <x-ui.field name="to" label="Hasta" type="date" wire:model.live="to" />
                <x-ui.select name="type" label="Tipo de movimiento" :options="\App\Services\Meal\InventoryHistory::TYPES" :selected="$type" placeholder="Todos" wire:model.live="type" />
                <x-ui.select name="channel" label="Canal" :options="\App\Services\Meal\InventoryHistory::CHANNELS" :selected="$channel" placeholder="Todos" wire:model.live="channel" />
            </div>
        </x-slot:filters>
        @if ($this->activeFilters())<x-slot:strip><x-ui.applied-filters :filters="$this->activeFilters()" /></x-slot:strip>
@endif
        <div wire:loading.delay role="status">Cargando movimientos…</div>
        @if ($loadError)<x-ui.state variant="error"><x-slot:actions><x-ui.action wire:click="$refresh">Reintentar</x-ui.action></x-slot:actions></x-ui.state>
        @elseif ($errors->any())<x-ui.state variant="error" title="Revisa los filtros" :message="$errors->first()" />
        @elseif ($movementsPage->isEmpty())<x-ui.state :variant="$total_movements ? 'filtered-empty' : 'empty'" :title="$total_movements ? 'Sin resultados para estos filtros' : 'Sin movimientos'" />
        @else
            <table class="md-table md-table--stack inventory-table">
                <caption class="visually-hidden">Kardex del ingrediente</caption>
                <thead>
                    <tr><th scope="col">Secuencia</th>
                    <th scope="col">Registro</th>
                    <th scope="col">Fecha efectiva</th>
                    <th scope="col">Documento</th>
                    <th scope="col">Concepto / origen</th>
                    <th scope="col">Canal</th>
                    <th scope="col">Unidad</th>
                    <th scope="col" class="inventory-table__number">Saldo anterior</th>
                    <th scope="col" class="inventory-table__number">Entrada</th>
                    <th scope="col" class="inventory-table__number">Salida</th>
                    <th scope="col" class="inventory-table__number">Saldo posterior</th></tr>
                </thead>
                <tbody>
                @php $lastSegment = '__start'; @endphp
                @foreach ($movementsPage as $movement)
                    @php $segment = $movement['legacy'] ? 'legacy' : $movement['segment_id']; @endphp
                    @if ($lastSegment !== $segment)
                        <tr class="inventory-table__segment" wire:key="segment-{{ $movement['id'] }}"><th colspan="11" scope="colgroup">{{ $movement['legacy'] ? 'Historial anterior a la apertura · Saldo no disponible' : 'Tramo en '.$movement['unit'] }}</th></tr>
                        @php $lastSegment = $segment; @endphp
                    @endif
                    <tr wire:key="inventory-movement-{{ $movement['id'] }}"><td data-label="Secuencia"><span class="inventory-table__label" aria-hidden="true">Secuencia</span><div>{{ $movement['sequence'] ?? 'Sin secuencia' }}</div></td>
                        <td data-label="Registro"><span class="inventory-table__label" aria-hidden="true">Registro</span><div>{{ \Carbon\Carbon::parse($movement['document']['recorded_at'])->format('d/m/Y H:i:s') }}</div></td>
                        <td data-label="Fecha efectiva"><span class="inventory-table__label" aria-hidden="true">Fecha efectiva</span><div>{{ $movement['document']['effective_date'] ? \Carbon\Carbon::parse($movement['document']['effective_date'])->format('d/m/Y') : ($movement['document']['effective_at'] ? \Carbon\Carbon::parse($movement['document']['effective_at'])->format('d/m/Y H:i:s') : 'Sin registrar') }}</div></td>
                        <td data-label="Documento"><span class="inventory-table__label" aria-hidden="true">Documento</span><div><x-ui.action variant="text" :href="route('meals.inventory.show', ['operation' => $movement['operation_id'], 'return' => $documentReturn])" wire:navigate>Ver documento</x-ui.action><span class="md-table__meta">{{ $movement['document']['type_label'] }}</span><span class="inventory-table__id">{{ $movement['operation_id'] }}</span></div></td>
                        <td data-label="Concepto / origen"><span class="inventory-table__label" aria-hidden="true">Concepto / origen</span><div>{{ $movement['document']['reason'] }}<span class="md-table__meta">{{ $movement['name'] }} · {{ $movement['document']['origin_label'] }} · {{ $movement['document']['origin']['reference'] ?? 'Sin referencia' }}</span>@if ($movement['compensates_movement_id'])<span class="md-table__meta">Compensación del movimiento original.</span>@endif</div></td>
                        <td data-label="Canal"><span class="inventory-table__label" aria-hidden="true">Canal</span><div>{{ $movement['document']['channel_label'] }}</div></td>
                        <td data-label="Unidad"><span class="inventory-table__label" aria-hidden="true">Unidad</span><div>{{ $movement['unit'] }}</div></td>
                        <td data-label="Saldo anterior"><span class="inventory-table__label" aria-hidden="true">Saldo anterior</span><div><span class="inventory-table__quantity">{{ $movement['balance_before'] === null ? 'Saldo no disponible' : $movement['balance_before'] }}</span></div></td>
                        <td data-label="Entrada"><span class="inventory-table__label" aria-hidden="true">Entrada</span><div><span class="inventory-table__quantity">{{ (float) $movement['delta'] > 0 ? $movement['delta'] : '—' }}</span></div></td>
                        <td data-label="Salida"><span class="inventory-table__label" aria-hidden="true">Salida</span><div><span class="inventory-table__quantity">{{ (float) $movement['delta'] < 0 ? ltrim($movement['delta'], '-') : '—' }}</span></div></td>
                        <td data-label="Saldo posterior"><span class="inventory-table__label" aria-hidden="true">Saldo posterior</span><div><span class="inventory-table__quantity">{{ $movement['balance_after'] === null ? 'Saldo no disponible' : $movement['balance_after'] }}</span></div></td></tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.management-card>
</x-module-shell>
