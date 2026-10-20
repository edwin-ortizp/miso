@props([
    'title',
    'icon',
    'id' => null,
    'count' => null,
    'search' => null,
    'searchPlaceholder' => 'Buscar',
    'activeFilters' => 0,
    'paginator' => null,
    'noun' => 'registros',
    'perPageOptions' => [10, 25, 50, 100],
    'perPageModel' => 'perPage',
    'alpine' => '',
    'onFiltersOpen' => '',
    'flush' => false,
    'sortModel' => null,
    'sortOptions' => [],
    'sortValue' => null,
    'refreshable' => true,
    'clear' => 'clearFilters',
])

@php
    /*
     * Card de gestión (estándar transversal para vistas que consultan y administran varios registros).
     * Header: ícono, título, contador | búsqueda, Filtros | ⋮. Contenido. Footer: rango, filas por página y paginación.
     * Las acciones de creación NO van aquí: usan x-module-actions / x-ui.fab.
     */
    $cardId = $id ?? 'mcard-'.\Illuminate\Support\Str::slug($title);
    $hasFilters = isset($filters);
    $hasMenu = $refreshable || isset($menu);
    $hasHeaderAction = isset($headerAction);
    $hasSort = filled($sortModel) && $sortOptions !== [];
    $sortLabel = $sortOptions[$sortValue] ?? reset($sortOptions);
    $isLengthAware = $paginator instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator;
    $state = trim('filtersOpen: false, searching: false'.($alpine !== '' ? ', '.$alpine : ''));
@endphp

<article {{ $attributes->class(['md-mcard']) }} id="{{ $cardId }}" aria-labelledby="{{ $cardId }}-title"
         x-data="{ {{ $state }} }"
         @keydown.escape="searching = false">
    <header class="md-mcard__head" :class="{ 'is-searching': searching }">
        <div class="md-mcard__title">
            <span class="md-mcard__icon" aria-hidden="true"><i class="bi {{ $icon }}"></i></span>
            <h2 class="md-mcard__heading" id="{{ $cardId }}-title">
                <span class="md-mcard__heading-text">{{ $title }}</span>
                @if (filled($count))
                    <span class="md-mcard__count" title="Registros visibles del total"><span aria-hidden="true">·</span> {{ $count }}</span>
                @endif
            </h2>
        </div>

        @if ($search || $hasSort || $hasFilters || $hasMenu)
            <span class="md-mcard__divider" aria-hidden="true"></span>
            <div class="md-mcard__tools">
                @if ($search)
                    <div class="md-mcard__search" role="search">
                        <i class="bi bi-search" aria-hidden="true"></i>
                        <label class="visually-hidden" for="{{ $cardId }}-search">{{ $searchPlaceholder }}</label>
                        <input type="search" id="{{ $cardId }}-search" placeholder="{{ $searchPlaceholder }}"
                               wire:model.live.debounce.300ms="{{ $search }}" x-ref="search">
                        <button type="button" class="md-btn-icon md-btn--sm md-mcard__search-close" @click="searching = false" aria-label="Cerrar búsqueda" title="Cerrar búsqueda">
                            <i class="bi bi-x-lg" aria-hidden="true"></i>
                        </button>
                    </div>
                    <button type="button" class="md-btn-icon md-mcard__search-toggle" @click="searching = true; $nextTick(() => $refs.search.focus())"
                            :aria-expanded="searching.toString()" aria-label="{{ $searchPlaceholder }}" title="{{ $searchPlaceholder }}">
                        <i class="bi bi-search" aria-hidden="true"></i>
                    </button>
                @endif

                @if ($hasSort)
                    {{-- Ordenar: mismo aspecto que Filtros, a su izquierda. --}}
                    <div class="md-menu md-mcard__sort" x-data="{ open: false }" @click.outside="open = false"
                         @keydown.escape.stop="if (open) { open = false; $refs.sortTrigger.focus() }">
                        <button type="button" x-ref="sortTrigger" class="md-btn-text md-mcard__filter" aria-haspopup="menu"
                                :aria-expanded="open.toString()" aria-label="Ordenar por {{ $sortLabel }}" title="Ordenar" @click="open = !open">
                            <i class="bi bi-sort-down" aria-hidden="true"></i><span class="md-mcard__filter-label">{{ $sortLabel }}</span>
                        </button>
                        <div class="md-menu__surface" role="menu" x-cloak x-show="open" x-transition.opacity.duration.120ms @click="open = false">
                            @foreach ($sortOptions as $sortKey => $optionLabel)
                                <button type="button" role="menuitemradio" aria-checked="{{ $sortKey === $sortValue ? 'true' : 'false' }}"
                                        class="md-menu__item" wire:click="$set('{{ $sortModel }}', @js($sortKey))">
                                    <i class="bi {{ $sortKey === $sortValue ? 'bi-check2' : 'bi-dot' }}" aria-hidden="true"></i><span>{{ $optionLabel }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($hasFilters)
                    <button type="button" class="md-btn-text md-mcard__filter" data-popover-trigger aria-haspopup="dialog" aria-controls="{{ $cardId }}-filters"
                            x-on:click="if (! filtersOpen) { {{ $onFiltersOpen }} } filtersOpen = ! filtersOpen" :aria-expanded="filtersOpen.toString()"
                            aria-label="Filtros" title="Filtros">
                        <i class="bi bi-sliders" aria-hidden="true"></i><span class="md-mcard__filter-label">Filtros</span>
                        @if ($activeFilters > 0)
                            <x-ui.badge placement="corner" :label="$activeFilters.' '.($activeFilters === 1 ? 'filtro activo' : 'filtros activos')">{{ $activeFilters }}</x-ui.badge>
                        @endif
                    </button>
                    <x-ui.popover state="filtersOpen" title="Filtros" :id="$cardId.'-filters'">
                        {{ $filters }}
                        @isset($filterActions)
                            <x-slot:actions>{{ $filterActions }}</x-slot:actions>
                        @endisset
                    </x-ui.popover>
                @endif

                @if ($hasMenu)
                    @if ($search || $hasFilters)
                        <span class="md-mcard__divider" aria-hidden="true"></span>
                    @endif
                    <x-ui.menu :label="'Más acciones de '.\Illuminate\Support\Str::lower($title)">
                        @if ($refreshable)
                            <x-ui.menu-item icon="bi-arrow-clockwise" wire:click="$refresh" wire:loading.attr="disabled" wire:target="$refresh">Actualizar tabla</x-ui.menu-item>
                        @endif
                        @isset($menu)
                            @if ($refreshable)<x-ui.menu-divider />@endif
                            {{ $menu }}
                        @endisset
                    </x-ui.menu>
                @endif
            </div>
        @endif

        @if ($hasHeaderAction)
            {{-- Acción de texto hacia la vista completa («Ver todas»). --}}
            <div class="md-mcard__header-action">{{ $headerAction }}</div>
        @endif
    </header>

    {{-- Filtros aplicados: chips del componente (slot `strip`) + búsqueda activa, que también filtra. --}}
    @if ($search)
        <div class="md-mcard__strip" role="group" aria-label="Filtros aplicados"
             @unless (isset($strip)) x-cloak x-show="$wire.{{ $search }}" @endunless>
            <span class="md-chip md-chip-input md-applied-chip" x-cloak x-show="$wire.{{ $search }}">
                <i class="bi bi-search" aria-hidden="true"></i>
                <span>Búsqueda: «<span x-text="$wire.{{ $search }}"></span>»</span>
                <button type="button" class="md-btn-icon md-btn--sm md-applied-chip__remove"
                        x-on:click="$wire.set(@js($search), '')" aria-label="Quitar búsqueda" title="Quitar filtro">
                    <i class="bi bi-x" aria-hidden="true"></i>
                </button>
            </span>
            {{ $strip ?? '' }}
            <button type="button" class="md-btn-text md-btn--sm md-applied-clear"
                    x-on:click="$wire.set(@js($search), '', {{ isset($strip) ? 'false' : 'true' }}){{ isset($strip) ? '; $wire.'.$clear.'()' : '' }}">Limpiar filtros</button>
        </div>
    @elseif (isset($strip))
        <div class="md-mcard__strip" role="group" aria-label="Filtros aplicados">
            {{ $strip }}
            <button type="button" class="md-btn-text md-btn--sm md-applied-clear" wire:click="{{ $clear }}">Limpiar filtros</button>
        </div>
    @endif

    <div @class(['md-mcard__body', 'md-mcard__body--flush' => $flush])>{{ $slot }}</div>

    @if ($isLengthAware || isset($footer))
        <footer class="md-mcard__foot">
            @isset($footer)
                {{ $footer }}
            @else
                <p class="md-mcard__range">
                    @if ($paginator->total() > 0)
                        Mostrando <strong>{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</strong> de <strong>{{ $paginator->total() }}</strong> {{ $noun }}
                    @else
                        Sin {{ $noun }}
                    @endif
                </p>
                <label class="md-mcard__rows">
                    <span>Filas por página</span>
                    <select wire:model.live="{{ $perPageModel }}" aria-label="Filas por página">
                        @foreach ($perPageOptions as $option)
                            <option value="{{ $option }}" @selected($paginator->perPage() === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
                {{ $paginator->onEachSide(1)->links('partials.ui.management-pagination') }}
            @endisset
        </footer>
    @endif
</article>
