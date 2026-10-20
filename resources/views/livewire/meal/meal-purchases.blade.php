<x-module-shell module="meals">
    <x-slot:actions>
        <x-module-actions :primary="['label' => 'Registrar compra', 'icon' => 'bi-receipt', 'event' => 'open-purchase-editor']" />
    </x-slot:actions>
    <x-ui.management-card id="purchase-history" title="Compras realizadas" icon="bi-receipt" :count="'('.$purchases->total().' / '.$totalPurchases.')'" search="search" search-placeholder="Buscar compras" :paginator="$purchases" noun="compras" :active-filters="(int) filled($from) + (int) filled($to) + (int) filled($storeId) + (int) filled($itemId)">
        <x-slot:filters>
            <div class="d-flex flex-column gap-3">
                <div class="md-field-pair">
                    <x-ui.field name="from" label="Desde" type="date" wire:model.live="from" />
                    <x-ui.field name="to" label="Hasta" type="date" wire:model.live="to" />
                </div>
                <x-ui.select name="storeId" label="Tienda" :options="$stores" :selected="$storeId" placeholder="Todas" wire:model.live="storeId" />
                <x-ui.select name="itemId" label="Producto" :options="$products" :selected="$itemId" placeholder="Todos" wire:model.live="itemId" />
            </div>
        </x-slot:filters>
        @php
            $appliedChips = array_values(array_filter([
                $from !== '' ? ['key' => 'from', 'value' => null, 'label' => 'Desde: '.\Carbon\Carbon::parse($from)->translatedFormat('j M Y'), 'icon' => 'bi-calendar-event'] : null,
                $to !== '' ? ['key' => 'to', 'value' => null, 'label' => 'Hasta: '.\Carbon\Carbon::parse($to)->translatedFormat('j M Y'), 'icon' => 'bi-calendar-event'] : null,
                $storeId !== '' ? ['key' => 'storeId', 'value' => null, 'label' => 'Tienda: '.($stores[$storeId] ?? $storeId), 'icon' => 'bi-shop'] : null,
                $itemId !== '' ? ['key' => 'itemId', 'value' => null, 'label' => 'Producto: '.($products[$itemId] ?? $itemId), 'icon' => 'bi-basket'] : null,
            ]));
        @endphp
        @if ($appliedChips !== [])
            <x-slot:strip>
                <x-ui.applied-filters :filters="$appliedChips" />
            </x-slot:strip>
        @endif
        <div class="purchase-history-list">
            @forelse ($purchases as $purchase)
                <a class="purchase-history-row" href="{{ route('meals.purchases.show', ['purchase' => $purchase->id, 'history' => $historyState]) }}" wire:navigate wire:key="purchase-{{ $purchase->id }}">
                    <span class="purchase-history-row__icon" aria-hidden="true"><i class="bi bi-receipt"></i></span>
                    <span class="purchase-history-row__body"><strong>{{ $purchase->store?->name ?? 'Sin tienda' }}</strong><span>{{ $purchase->purchased_at->translatedFormat('j M Y') }} · {{ $purchase->lines_count }} {{ $purchase->lines_count === 1 ? 'ítem' : 'ítems' }}</span></span>
                    <strong class="purchase-history-row__total">{{ $purchase->total_paid === null ? 'Sin registrar' : '$ '.number_format((float) $purchase->total_paid, 2, ',', '.') }}</strong>
                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                </a>
            @empty
                <p class="md-body-medium p-3">No hay compras registradas para estos filtros.</p>
            @endforelse
        </div>
    </x-ui.management-card>
    <livewire:meal.purchase-editor :key="'purchase-editor-history'" />
</x-module-shell>
