@php
    use App\Models\ShoppingItemPrice;
    $money = fn ($value) => '$'.number_format((float) $value, 0, ',', '.');
    $perBaseMoney = fn ($value) => '$'.number_format((float) $value, $value < 100 ? 2 : 0, ',', '.');
@endphp
<x-module-shell module="meals">
    <x-slot:actions>
        <x-module-actions :primary="['label' => 'Registrar precio', 'icon' => 'bi-receipt', 'action' => 'openForm']"
                          :secondary="[['label' => 'Tiendas', 'icon' => 'bi-shop', 'event' => 'open-store-catalog']]" />
        <livewire:meal.store-catalog />
    </x-slot:actions>

    <div class="compare-header mb-3">
        <a href="{{ route('meals.ingredients') }}" wire:navigate class="md-btn-text"><i class="bi bi-arrow-left" aria-hidden="true"></i> Ingredientes</a>
        <h2 class="md-headline-small mb-0">{{ $product->name }}</h2>
        <span class="md-body-medium meal-muted">
            @if ($comparisonLabel)
                Precios comparados por {{ $comparisonLabel }}
            @else
                Define la unidad base del producto para poder comparar precios.
            @endif
        </span>
    </div>

    <x-ui.management-card id="meal-compare" title="Comparación" icon="bi-bar-chart-steps" :count="'('.$comparable->count().')'"
                          :active-filters="collect([$storeFilter, $brandFilter])->filter()->count()" alpine="openMenu: null">
        @if ($stores->isNotEmpty() || $brands->isNotEmpty())
            <x-slot:filters>
                <div class="md-chip-rail md-mcard__filter-chips" role="group" aria-label="Filtros de comparación" @click.outside="openMenu = null">
                    <div class="md-chip-menu" :class="{ 'open': openMenu === 'store' }">
                        <button @click="openMenu = openMenu === 'store' ? null : 'store'" class="md-chip md-chip-filter {{ $storeFilter ? 'selected' : '' }}">
                            <i class="bi bi-shop"></i> {{ $stores[$storeFilter] ?? 'Tienda' }}
                            <i class="bi bi-chevron-down md-chip-menu__arrow"></i>
                        </button>
                        <div x-show="openMenu === 'store'" x-transition x-cloak class="md-chip-menu__dropdown">
                            <button wire:click="$set('storeFilter', '')" @click="openMenu = null" class="md-chip-menu__item {{ $storeFilter === '' ? 'active' : '' }}">Todas</button>
                            @foreach ($stores as $storeId => $storeName)
                                <button wire:click="$set('storeFilter', '{{ $storeId }}')" @click="openMenu = null" class="md-chip-menu__item {{ $storeFilter === $storeId ? 'active' : '' }}">{{ $storeName }}</button>
                            @endforeach
                        </div>
                    </div>
                    <div class="md-chip-menu" :class="{ 'open': openMenu === 'brand' }">
                        <button @click="openMenu = openMenu === 'brand' ? null : 'brand'" class="md-chip md-chip-filter {{ $brandFilter ? 'selected' : '' }}">
                            <i class="bi bi-award"></i> {{ $brands[$brandFilter] ?? 'Marca' }}
                            <i class="bi bi-chevron-down md-chip-menu__arrow"></i>
                        </button>
                        <div x-show="openMenu === 'brand'" x-transition x-cloak class="md-chip-menu__dropdown">
                            <button wire:click="$set('brandFilter', '')" @click="openMenu = null" class="md-chip-menu__item {{ $brandFilter === '' ? 'active' : '' }}">Todas</button>
                            @foreach ($brands as $brandId => $brandName)
                                <button wire:click="$set('brandFilter', '{{ $brandId }}')" @click="openMenu = null" class="md-chip-menu__item {{ $brandFilter === $brandId ? 'active' : '' }}">{{ $brandName }}</button>
                            @endforeach
                        </div>
                    </div>
                </div>
            </x-slot:filters>
        @php
            $appliedChips = array_values(array_filter([
                $storeFilter !== '' ? ['key' => 'storeFilter', 'value' => null, 'label' => 'Tienda: '.($stores[$storeFilter] ?? $storeFilter), 'icon' => 'bi-shop'] : null,
                $brandFilter !== '' ? ['key' => 'brandFilter', 'value' => null, 'label' => 'Marca: '.($brands[$brandFilter] ?? $brandFilter), 'icon' => 'bi-award'] : null,
            ]));
        @endphp
        @if ($appliedChips !== [])
            <x-slot:strip>
                <x-ui.applied-filters :filters="$appliedChips" />
            </x-slot:strip>
        @endif
        @endif

        @if ($comparable->isNotEmpty())
            <table class="md-table md-table--stack">
                <thead>
                    <tr>
                        <th scope="col">Variante</th>
                        <th scope="col">Tienda</th>
                        <th scope="col">Precio</th>
                        <th scope="col">Por {{ $comparisonLabel }}</th>
                        <th scope="col">Dato</th>
                        <th scope="col" class="md-table__actions"><span class="visually-hidden">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($comparable as $offer)
                        @php
                            $variant = $offer['variant'];
                            $price = $offer['price'];
                            $perPiece = $variant->pricePerPiece($price);
                        @endphp
                        <tr wire:key="offer-{{ $price->id }}" class="{{ $loop->first ? 'compare-row--best' : '' }}">
                            <td class="md-table__title">
                                @if ($loop->first)<span class="md-chip md-chip--small compare-best"><i class="bi bi-trophy-fill" aria-hidden="true"></i> Más barata</span>@endif
                                {{ $variant->label($product->base_unit) }}
                                @if ($variant->is_preferred)<span class="md-table__meta"><i class="bi bi-star-fill" aria-hidden="true"></i> Preferida</span>@endif
                            </td>
                            <td>{{ $price->store?->name }}</td>
                            <td class="md-table__nowrap">{{ $money($price->amount) }}</td>
                            <td class="md-table__nowrap">
                                <strong>{{ $perBaseMoney($offer['per_base']) }}</strong>
                                @if (! $loop->first && $offer['difference'] > 0)<span class="md-table__meta">+{{ number_format($offer['difference'], 1, ',', '.') }} %</span>@endif
                                @if ($perPiece)<span class="md-table__meta">{{ $perBaseMoney($perPiece) }} / unidad</span>@endif
                            </td>
                            <td>
                                {{ $price->observed_on->translatedFormat('j M Y') }} · {{ ShoppingItemPrice::SOURCES[$price->source] ?? $price->source }}
                                @if ($price->isStale())<span class="md-table__meta compare-warning"><i class="bi bi-clock-history" aria-hidden="true"></i> Más de {{ ShoppingItemPrice::STALE_AFTER_DAYS }} días</span>@endif
                                @unless ($price->isVerified())<span class="md-table__meta compare-warning"><i class="bi bi-question-circle" aria-hidden="true"></i> Sin verificar</span>@endunless
                            </td>
                            <td class="md-table__actions">
                                <x-ui.row-actions :label="'Más acciones del precio de '.$variant->label($product->base_unit)">
                                    <x-slot:primary wire:click="openForm('{{ $variant->id }}')">Nuevo precio</x-slot:primary>
                                    @unless ($price->isVerified())
                                        <x-ui.menu-item icon="bi-patch-check" wire:click="verifyPrice('{{ $price->id }}')">Marcar verificado</x-ui.menu-item>
                                    @endunless
                                </x-ui.row-actions>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <x-ui.state variant="{{ \App\Support\Ui\DataState::EMPTY }}" icon="bi-bar-chart-steps" title="Nada que comparar todavía"
                        message="Registra el contenido y al menos un precio de cada variante para compararlas." />
        @endif
    </x-ui.management-card>

    @if ($pending->isNotEmpty() || $withoutPrices->isNotEmpty())
        <section class="md-card-elevated p-3 mt-3 compare-pending">
            <h3 class="md-title-small"><i class="bi bi-exclamation-diamond" aria-hidden="true"></i> Pendientes de completar</h3>
            <ul class="mb-0">
                @foreach ($pending as $variant)
                    <li>{{ $variant->label($product->base_unit) }} — falta el contenido; no entra en la comparación.</li>
                @endforeach
                @foreach ($withoutPrices as $variant)
                    <li>{{ $variant->label($product->base_unit) }} — sin precios registrados.</li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($history->isNotEmpty())
        <details class="md-card-elevated p-3 mt-3">
            <summary class="md-title-small compare-history__summary"><i class="bi bi-clock-history" aria-hidden="true"></i> Historial de precios ({{ $history->count() }})</summary>
            <table class="md-table md-table--stack mt-2">
                <thead>
                    <tr><th scope="col">Fecha</th><th scope="col">Variante</th><th scope="col">Tienda</th><th scope="col">Precio</th><th scope="col">Fuente</th></tr>
                </thead>
                <tbody>
                    @foreach ($history as $row)
                        <tr wire:key="history-{{ $row['price']->id }}">
                            <td class="md-table__date">{{ $row['price']->observed_on->translatedFormat('j M Y') }}</td>
                            <td>{{ $row['variant']->label($product->base_unit) }}</td>
                            <td>{{ $row['price']->store?->name }}</td>
                            <td class="md-table__nowrap">{{ $money($row['price']->amount) }}@if ($row['price']->paid) <span class="md-table__meta">pagado</span>@endif</td>
                            <td>{{ ShoppingItemPrice::SOURCES[$row['price']->source] ?? $row['price']->source }}{{ $row['price']->isVerified() ? ' · verificado' : '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </details>
    @endif

    <x-ui.form-dialog :open="$showForm" close="closeForm" submit-action="savePrice" id="compare-price-dialog"
                      title="Registrar precio" icon="bi-receipt">
        <div class="d-flex flex-column gap-3">
            <x-ui.select name="priceVariantId" label="Variante" :required="true" :options="$variantOptions->all()" :selected="$priceVariantId" wire:model="priceVariantId" />
            <x-ui.select name="priceStoreId" label="Tienda" :required="true" placeholder="Elige la tienda" :options="$storeOptions->all()" :selected="$priceStoreId"
                         help="¿No está? Agrégala desde el menú ⋮ › Tiendas." wire:model="priceStoreId" />
            <div class="md-field-pair">
                <x-ui.field name="priceAmount" label="Precio" type="number" step="0.01" min="0" :required="true" wire:model="priceAmount" />
                <x-ui.field name="priceDate" label="Fecha" type="date" :required="true" wire:model="priceDate" />
            </div>
            <x-ui.select name="priceSource" label="Fuente" :options="ShoppingItemPrice::SOURCES" :selected="$priceSource" wire:model="priceSource"
                         help="Un precio de ticket queda verificado." />
            <label class="md-checkbox"><input type="checkbox" wire:model="pricePaid"> Es el precio que pagué en una compra</label>
        </div>
    </x-ui.form-dialog>
</x-module-shell>
