<x-module-shell module="meals">
    <x-ui.management-card id="inventory-documents" title="Movimientos de inventario" icon="bi-journal-text" search="search" search-placeholder="Buscar referencia o ingrediente" :paginator="$documents" :count="'('.$documents->total().' / '.$totalDocuments.')'" noun="documentos" :active-filters="count($this->activeFilters())">
        <x-slot:filters>
            <div class="d-flex flex-column gap-3">
                <p class="md-body-small">Las fechas filtran la fecha efectiva; si no se conoce, se usa la de registro.</p>
                <x-ui.field name="from" label="Desde" type="date" wire:model.live="from" />
                <x-ui.field name="to" label="Hasta" type="date" wire:model.live="to" />
                <x-ui.select name="type" label="Tipo de movimiento" :options="\App\Services\Meal\InventoryHistory::TYPES" :selected="$type" placeholder="Todos" wire:model.live="type" />
                <x-ui.select name="channel" label="Canal" :options="\App\Services\Meal\InventoryHistory::CHANNELS" :selected="$channel" placeholder="Todos" wire:model.live="channel" />
                <x-ui.select name="itemId" label="Ingrediente" :options="$products" :selected="$itemId" placeholder="Todos" wire:model.live="itemId" />
            </div>
        </x-slot:filters>
        @if ($this->activeFilters())<x-slot:strip><x-ui.applied-filters :filters="$this->activeFilters()" /></x-slot:strip>
@endif
        <div wire:loading.delay role="status" aria-live="polite">Cargando documentos…</div>
        @if ($loadError)
            <x-ui.state variant="error" message="Intenta actualizar el registro."><x-slot:actions><x-ui.action wire:click="$refresh">Reintentar</x-ui.action></x-slot:actions></x-ui.state>
        @elseif ($errors->any())
            <x-ui.state variant="error" title="Revisa los filtros" :message="$errors->first()" />
        @elseif ($documents->isEmpty())
            <x-ui.state :variant="$totalDocuments ? 'filtered-empty' : 'empty'" :title="$totalDocuments ? 'Sin resultados para estos filtros' : 'Sin movimientos de inventario'" />
        @else
            <table class="md-table md-table--stack inventory-table">
                <caption class="visually-hidden">Documentos de inventario</caption>
                <thead>
                    <tr><th scope="col">Documento / ID</th>
                    <th scope="col">Registro</th>
                    <th scope="col">Fecha efectiva</th>
                    <th scope="col">Tipo</th>
                    <th scope="col">Origen / referencia</th>
                    <th scope="col">Canal</th>
                    <th scope="col" class="inventory-table__number">Productos</th>
                    <th scope="col" class="inventory-table__number">Líneas</th>
                    <th scope="col">Acciones</th></tr>
                </thead>
                <tbody>
                @foreach ($documents as $document)
                    <tr wire:key="inventory-document-{{ $document['id'] }}"><td data-label="Documento / ID"><span class="inventory-table__label" aria-hidden="true">Documento / ID</span><div><span class="inventory-table__id" title="{{ $document['id'] }}">{{ $document['id'] }}</span></div></td>
                        <td data-label="Registro"><span class="inventory-table__label" aria-hidden="true">Registro</span><div>{{ \Carbon\Carbon::parse($document['recorded_at'])->format('d/m/Y H:i:s') }}</div></td>
                        <td data-label="Fecha efectiva"><span class="inventory-table__label" aria-hidden="true">Fecha efectiva</span><div>{{ $document['effective_date'] ? \Carbon\Carbon::parse($document['effective_date'])->format('d/m/Y') : ($document['effective_at'] ? \Carbon\Carbon::parse($document['effective_at'])->format('d/m/Y H:i:s') : 'Sin registrar') }}</div></td>
                        <td data-label="Tipo"><span class="inventory-table__label" aria-hidden="true">Tipo</span><div>{{ $document['type_label'] }}<span class="md-table__meta">{{ $document['reason'] }}</span></div></td>
                        <td data-label="Origen / referencia"><span class="inventory-table__label" aria-hidden="true">Origen / referencia</span><div>{{ $document['origin_label'] }}<span class="md-table__meta">{{ $document['origin']['reference'] ?? 'Sin referencia' }}</span></div></td>
                        <td data-label="Canal"><span class="inventory-table__label" aria-hidden="true">Canal</span><div>{{ $document['channel_label'] }}</div></td>
                        <td data-label="Productos"><span class="inventory-table__label" aria-hidden="true">Productos</span><div><span class="inventory-table__quantity">{{ $document['product_count'] }}</span></div></td>
                        <td data-label="Líneas"><span class="inventory-table__label" aria-hidden="true">Líneas</span><div><span class="inventory-table__quantity">{{ $document['line_count'] }}</span></div></td>
                        <td data-label="Acciones"><span class="inventory-table__label" aria-hidden="true">Acciones</span><div><x-ui.action variant="text" :href="route('meals.inventory.show', ['operation' => $document['id'], 'return' => ['screen' => 'documents', 'filters' => $this->state()]])" wire:navigate :aria-label="'Abrir documento '.$document['id']">Abrir</x-ui.action></div></td></tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.management-card>
</x-module-shell>
