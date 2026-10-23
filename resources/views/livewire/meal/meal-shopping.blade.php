<x-module-shell module="meals">
    <x-slot:actions>
        <livewire:meal.bulk-ingredient-assistant context="shopping" />
        <x-module-actions :primary="['label' => 'Agregar a compras', 'icon' => 'bi-cart-plus', 'action' => 'openForm']"
                          :split="true" :secondary="[['label' => 'Registrar compra', 'icon' => 'bi-receipt', 'event' => 'open-purchase-editor', 'create' => true], ['label' => 'Generar compras del plan', 'icon' => 'bi-cart-plus', 'event' => 'meal-shopping-preview', 'create' => true], ['label' => 'Tiendas', 'icon' => 'bi-shop', 'event' => 'open-store-catalog']]" />
        <livewire:meal.store-catalog />
        <livewire:meal.purchase-editor :key="'purchase-editor-shopping'" />
        <livewire:meal.meal-plan-shopping-editor :key="'meal-plan-shopping-editor'" />
    </x-slot:actions>

    <x-ui.management-card id="meal-shopping" title="Por comprar" icon="bi-cart3" :count="'('.$totalItems.')'"
                          search="search" search-placeholder="Buscar en lista de compras" :active-filters="$placeFilter !== '' ? 1 : 0"
                          :paginator="$items" noun="artículos" alpine="openMenu: null">
        <x-slot:filters>
            <div class="md-chip-rail md-mcard__filter-chips" role="group" aria-label="Filtros de la compra" @click.outside="openMenu = null">
            <button wire:click="setViewMode('compact')"
                    class="md-chip md-chip-filter {{ $viewMode === 'compact' ? 'selected' : '' }}">
                <i class="bi bi-list"></i> Compacta
            </button>
            <button wire:click="setViewMode('grouped')"
                    class="md-chip md-chip-filter {{ $viewMode === 'grouped' ? 'selected' : '' }}">
                <i class="bi bi-collection"></i> Agrupada
            </button>

            @if ($viewMode === 'grouped')
                <div class="md-chip-rail__divider"></div>

                <button wire:click="toggleGroupBy" class="md-chip md-chip-filter selected">
                    <i class="bi bi-{{ $groupBy === 'category' ? 'tag' : 'geo-alt' }}"></i>
                    {{ $groupBy === 'category' ? 'Por categoría' : 'Por tienda' }}
                </button>
            @endif

            @if ($places->isNotEmpty())
                <div class="md-chip-rail__divider"></div>

                <div class="md-chip-menu" :class="{ 'open': openMenu === 'place' }">
                    <button @click="openMenu = openMenu === 'place' ? null : 'place'"
                            class="md-chip md-chip-filter {{ $placeFilter ? 'selected' : '' }}">
                        {{ $places[$placeFilter] ?? 'Tienda' }}
                        <i class="bi bi-chevron-down md-chip-menu__arrow"></i>
                    </button>
                    <div x-show="openMenu === 'place'" x-transition x-cloak class="md-chip-menu__dropdown">
                        <button wire:click="$set('placeFilter', '')" @click="openMenu = null"
                                class="md-chip-menu__item {{ $placeFilter === '' ? 'active' : '' }}">Todas</button>
                        @foreach ($places as $storeId => $storeName)
                            <button wire:click="$set('placeFilter', '{{ $storeId }}')" @click="openMenu = null"
                                    class="md-chip-menu__item {{ $placeFilter === $storeId ? 'active' : '' }}">{{ $storeName }}</button>
                        @endforeach
                    </div>
                </div>
            @endif
            </div>
        </x-slot:filters>
        @php
            $appliedChips = array_values(array_filter([
                $placeFilter !== '' ? ['key' => 'placeFilter', 'value' => null, 'label' => 'Tienda: '.($places[$placeFilter] ?? $placeFilter), 'icon' => 'bi-shop'] : null,
            ]));
        @endphp
        @if ($appliedChips !== [])
            <x-slot:strip>
                <x-ui.applied-filters :filters="$appliedChips" />
            </x-slot:strip>
        @endif

    {{-- Estimated cost of the next purchase --}}
    @if ($totalItems > 0)
        <div class="shopping-estimate mb-3">
            <span class="shopping-estimate__icon"><i class="bi bi-cash-coin"></i></span>
            <span>
                <small>Total estimado{{ $placeFilter ? ' en '.($places[$placeFilter] ?? '') : '' }}</small>
                <strong>${{ number_format($estimatedTotal, 0, ',', '.') }}</strong>
            </span>
            <small class="shopping-estimate__note">
                @if ($unpricedCount > 0)
                    <i class="bi bi-exclamation-circle"></i> {{ $unpricedCount }} {{ $unpricedCount === 1 ? 'artículo sin precio' : 'artículos sin precio' }}
                @else
                    {{ $placeFilter ? 'Precios de esta tienda' : 'Variante preferida o la más barata de cada artículo' }}
                @endif
            </small>
        </div>
    @endif

    {{-- Needed from meal plan --}}
    @if ($neededItems->isNotEmpty())
        <details class="shopping-needed mb-3">
            <summary>
                <span class="shopping-needed__icon"><i class="bi bi-calendar-week"></i></span>
                <span>
                    <strong>{{ $neededCount }} necesarios esta semana</strong>
                    <small>Calculados desde tus recetas, descontando lo que tienes en casa{{ $neededCost > 0 ? ' · faltante ≈ $'.number_format($neededCost, 0, ',', '.') : '' }}</small>
                </span>
                <i class="bi bi-chevron-down shopping-needed__arrow"></i>
            </summary>
            <div class="shopping-needed__content">
                @foreach ($neededItems as $needed)
                    @php $item = $needed['shopping_item']; @endphp
                    <span class="shopping-needed__item {{ $item->next_purchase || $needed['missing'] === 0.0 ? 'is-added' : '' }}">
                        {{ $item->name }}
                        <small>
                            @if ($needed['quantity'] === null)
                                ?
                            @elseif ($needed['missing'] > 0)
                                faltan {{ $item->formatQuantity($needed['missing']) }}
                            @else
                                cubierto ({{ $item->formatQuantity($needed['quantity']) }})
                            @endif
                        </small>
                        @if (! $item->next_purchase && $needed['missing'] !== 0.0)
                            <button wire:click="addSuggested('{{ $item->id }}')" title="Agregar a compras" aria-label="Agregar {{ $item->name }} a compras">
                                <i class="bi bi-plus-circle"></i>
                            </button>
                        @endif
                    </span>
                @endforeach
            </div>
        </details>
    @endif

    {{-- Below minimum stock --}}
    @if ($belowMinimum->isNotEmpty())
        <details class="shopping-needed shopping-needed--stock mb-3">
            <summary>
                <span class="shopping-needed__icon"><i class="bi bi-box-seam"></i></span>
                <span><strong>{{ $belowMinimum->count() }} bajo el mínimo</strong><small>Productos con stock en casa por debajo de su mínimo</small></span>
                <i class="bi bi-chevron-down shopping-needed__arrow"></i>
            </summary>
            <div class="shopping-needed__content">
                @foreach ($belowMinimum as $item)
                    <span class="shopping-needed__item">
                        {{ $item->name }}
                        <small>{{ $item->formatQuantity($item->stock) }} / mín. {{ $item->formatQuantity($item->min_stock) }}</small>
                        <button wire:click="addSuggested('{{ $item->id }}')" title="Agregar a compras" aria-label="Agregar {{ $item->name }} a compras">
                            <i class="bi bi-plus-circle"></i>
                        </button>
                    </span>
                @endforeach
            </div>
        </details>
    @endif

    {{-- Shopping list --}}
    @if ($grouped->isEmpty())
        <div class="md-card-elevated p-4 text-center">
            <i class="bi bi-cart3" style="font-size: 2rem; color: var(--md-sys-color-outline);"></i>
            <p class="md-body-large mt-2" style="color: var(--md-sys-color-on-surface-variant);">
                No hay artículos en tu lista de compras.
            </p>
            <p class="md-body-medium" style="color: var(--md-sys-color-on-surface-variant);">
                Marca ingredientes como "próxima compra" desde el tab <a href="{{ route('meals.ingredients') }}" class="md-link">Ingredientes</a>.
            </p>
        </div>
    @else
        <div class="shopping-list shopping-list--{{ $viewMode }}">
            @if ($viewMode === 'compact')
                @foreach ($items as $item)
                    @include('livewire.meal._shopping-item', ['rowKey' => $item->id])
                @endforeach
            @else
                @foreach ($grouped as $groupName => $groupItems)
                    <section class="shopping-group" wire:key="shopping-group-{{ md5((string) $groupName) }}">
                        <header class="shopping-group__header">
                            <span>
                                <i class="bi bi-{{ $groupBy === 'category' ? 'tag' : 'geo-alt' }}"></i>
                                @if ($groupBy === 'category')
                                    {{ $this->categoryOptions[$groupName] ?? $groupName ?: 'Sin categoría' }}
                                @else
                                    {{ $groupName ?: 'Sin tienda' }}
                                @endif
                            </span>
                            <small>{{ $groupItems->count() }}</small>
                        </header>
                        @foreach ($groupItems as $item)
                            @include('livewire.meal._shopping-item', ['rowKey' => md5($groupBy.'|'.$groupName.'|'.$item->id)])
                        @endforeach
                    </section>
                @endforeach
            @endif
        </div>
    @endif
    </x-ui.management-card>

    <x-slot:rail>
        <x-context-widget title="Resumen" icon="bi-cart3" tone="success">
            <div class="text-center mb-2">
                <span style="font-size: 2rem; font-weight: 700; color: var(--md-sys-color-primary);">{{ $totalItems }}</span>
                <span class="md-body-small d-block" style="color: var(--md-sys-color-on-surface-variant);">por comprar</span>
            </div>
            <dl class="md-context-list">
                <div><dt>Total estimado</dt><dd>${{ number_format($estimatedTotal, 0, ',', '.') }}</dd></div>
                @if ($unpricedCount > 0)
                    <div><dt>Sin precio</dt><dd>{{ $unpricedCount }}</dd></div>
                @endif
            </dl>
            @if ($neededCount > 0)
                <dl class="md-context-list">
                    <div><dt>Necesarios (recetas)</dt><dd>{{ $neededCount }}</dd></div>
                </dl>
            @endif
        </x-context-widget>

        <x-context-widget title="Vistas relacionadas" icon="bi-signpost-split">
            <div class="md-context-links">
                <a href="{{ route('meals.ingredients') }}"><i class="bi bi-basket"></i> Ingredientes</a>
                <a href="{{ route('meals.recipes') }}"><i class="bi bi-book"></i> Recetas</a>
            </div>
        </x-context-widget>
    </x-slot:rail>

    @include('livewire.meal.shopping-dialogs')

</x-module-shell>
