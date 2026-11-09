<x-module-shell module="meals" title="Documento de inventario" :back="['href' => $backUrl, 'label' => 'Volver al registro']">
    @if ($loadError)
        <x-ui.state variant="error" message="Intenta actualizar el documento."><x-slot:actions><x-ui.action wire:click="$refresh">Reintentar</x-ui.action></x-slot:actions></x-ui.state>
    @elseif ($document)
        <article class="inventory-document" aria-labelledby="inventory-document-title">
            <header class="inventory-document__header">
                <div class="inventory-document__identity">
                    <p class="md-label-large">Documento de inventario</p>
                    <h2 id="inventory-document-title" class="md-headline-small">{{ $document['type_label'] }}</h2>
                    <p class="md-body-small">{{ $document['product_count'] }} productos · {{ $document['line_count'] }} líneas · {{ $document['movement_count'] }} movimientos</p>
                </div>
                <div class="inventory-document__id" x-data="{ copied: false }">
                    <x-ui.field id="inventory-document-id" name="documentId" label="ID del documento" :value="$document['id']" readonly />
                    <x-ui.action variant="text" icon="bi-copy" @click="const input = document.getElementById('inventory-document-id'); input.select(); if (navigator.clipboard) navigator.clipboard.writeText(input.value).then(() => { copied = true; $el.focus(); }).catch(() => copied = false)"><span x-text="copied ? 'ID copiado' : 'Copiar ID'">Copiar ID</span></x-ui.action>
                </div>
            </header>
            <dl class="inventory-document__meta">
                <div><dt>Registro</dt><dd>{{ \Carbon\Carbon::parse($document['recorded_at'])->format('d/m/Y H:i:s') }}</dd></div>
                <div><dt>Fecha efectiva</dt><dd>{{ $document['effective_date'] ? \Carbon\Carbon::parse($document['effective_date'])->format('d/m/Y') : ($document['effective_at'] ? \Carbon\Carbon::parse($document['effective_at'])->format('d/m/Y H:i:s') : 'Sin registrar') }}</dd></div>
                <div><dt>Canal</dt><dd>{{ $document['channel_label'] }}</dd></div>
                <div><dt>Origen / referencia</dt><dd>{{ $document['origin_label'] }} · {{ $document['origin']['reference'] ?? $document['origin']['id'] ?? 'Sin referencia' }}
                    @if ($document['origin_url'])<x-ui.action variant="text" :href="$document['origin_url']" wire:navigate>Consultar origen</x-ui.action>@endif
                </dd></div>
                <div><dt>Motivo</dt><dd>{{ $document['reason'] }}</dd></div>
            </dl>
            @if ($document['legacy'])<p class="inventory-document__notice md-body-small">Historial anterior a la apertura. Los saldos no están disponibles.</p>@endif
        <x-ui.management-card id="inventory-document-lines" title="Líneas del documento" icon="bi-list-ol" :paginator="$linesPage" noun="líneas">
            <div wire:loading.delay role="status">Cargando líneas…</div>
            @if ($linesPage->isEmpty())<x-ui.state variant="empty" title="Sin líneas disponibles" />
@else
            <table class="md-table md-table--stack inventory-table">
                <caption class="visually-hidden">Líneas del documento de inventario</caption>
                <thead>
                    <tr><th scope="col">Línea</th>
                    <th scope="col">Ingrediente / variante</th>
                    <th scope="col">Unidad</th>
                    <th scope="col" class="inventory-table__number">Saldo anterior</th>
                    <th scope="col" class="inventory-table__number">Entrada</th>
                    <th scope="col" class="inventory-table__number">Salida</th>
                    <th scope="col" class="inventory-table__number">Saldo posterior</th>
                    <th scope="col">Acciones</th></tr>
                </thead>
                <tbody>
                @foreach ($linesPage as $line)
                    <tr wire:key="inventory-line-{{ $line['line_id'] }}"><td data-label="Línea"><span class="inventory-table__label" aria-hidden="true">Línea</span><div>{{ $line['position'] }}</div></td>
                        <td data-label="Ingrediente / variante"><span class="inventory-table__label" aria-hidden="true">Ingrediente / variante</span><div>{{ $line['name'] }}@if ($line['variant_label'])<span class="md-table__meta">{{ $line['variant_label'] }}</span>@endif @if ($line['compensates_movement_id'])<span class="md-table__meta">Movimiento compensatorio del documento original.</span>@endif</div></td>
                        <td data-label="Unidad"><span class="inventory-table__label" aria-hidden="true">Unidad</span><div>{{ $line['unit'] }}</div></td>
                        <td data-label="Saldo anterior"><span class="inventory-table__label" aria-hidden="true">Saldo anterior</span><div><span class="inventory-table__quantity">{{ $line['balance_before'] === null ? 'Saldo no disponible' : $line['balance_before'] }}</span></div></td>
                        <td data-label="Entrada"><span class="inventory-table__label" aria-hidden="true">Entrada</span><div><span class="inventory-table__quantity">{{ (float) $line['delta'] > 0 ? $line['delta'] : '—' }}</span></div></td>
                        <td data-label="Salida"><span class="inventory-table__label" aria-hidden="true">Salida</span><div><span class="inventory-table__quantity">{{ (float) $line['delta'] < 0 ? ltrim($line['delta'], '-') : '—' }}</span></div></td>
                        <td data-label="Saldo posterior"><span class="inventory-table__label" aria-hidden="true">Saldo posterior</span><div><span class="inventory-table__quantity">{{ $line['balance_after'] === null ? 'Saldo no disponible' : $line['balance_after'] }}</span></div></td>
                        <td data-label="Acciones"><span class="inventory-table__label" aria-hidden="true">Acciones</span><div>@if ($line['kardex_available'])<x-ui.action variant="text" :href="route('meals.inventory.history', ['item' => $line['shopping_item_id'], 'return' => $kardexReturn])" wire:navigate>Ver Kardex</x-ui.action>@else<span>Producto histórico</span>@endif</div></td></tr>
                @endforeach
                </tbody>
            </table>
            @endif
        </x-ui.management-card>
            <footer class="inventory-document__footer">
                <h3 class="md-title-small">Observaciones</h3>
                <p>{{ $document['reason'] ?: 'Sin observaciones' }}</p>
                @if ($document['corrects_operation_id'])<x-ui.action variant="text" :href="route('meals.inventory.show', ['operation' => $document['corrects_operation_id']])" wire:navigate>Ver documento original</x-ui.action>@endif
                @foreach ($corrections as $correction)<x-ui.action variant="text" :href="route('meals.inventory.show', ['operation' => $correction['id']])" wire:navigate>Consultar corrección · {{ \Carbon\Carbon::parse($correction['recorded_at'])->format('d/m/Y H:i:s') }}</x-ui.action>@endforeach
            </footer>
        </article>
    @endif
</x-module-shell>
