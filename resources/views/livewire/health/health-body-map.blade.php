@php
    $legend = [0 => 'Sin eventos', 1 => '1 evento', 2 => '2–3 eventos', 3 => '4–5 eventos', 4 => '6 o más'];
    $events = fn (int $count) => $count.' '.($count === 1 ? 'evento' : 'eventos');
@endphp

<x-module-shell module="health" class="health-page">
    <livewire:game.guardian-card module="health" variant="top" :section-context="$guardianContext" />
    <x-slot:actions>
        <x-module-actions :primary="['label' => 'Registrar evento', 'icon' => 'bi-plus-lg', 'href' => route('health', ['new' => 1])]" :fab-always="true" />
    </x-slot:actions>

    <section class="health-timeline-section" aria-labelledby="health-body-title">
        <div class="health-toolbar" x-data="{ filtersOpen: false }">
            <h2 id="health-body-title" class="health-toolbar__title">
                <i class="bi bi-person-standing" aria-hidden="true"></i>
                <span>Mapa corporal</span>
                <span class="health-count" title="Eventos con zona del cuerpo en el periodo">({{ $events($total) }})</span>
            </h2>

            <div class="health-toolbar__filters">
                <x-ui.action variant="outlined" icon="bi-sliders" class="health-filter-button"
                             data-popover-trigger aria-haspopup="dialog" aria-controls="health-body-filters"
                             x-on:click="filtersOpen = !filtersOpen" x-bind:aria-expanded="filtersOpen.toString()">
                    Filtros
                    @if (count($activeFilters) > 0)
                        <x-ui.badge placement="corner" :label="count($activeFilters).' '.(count($activeFilters) === 1 ? 'filtro activo' : 'filtros activos')">{{ count($activeFilters) }}</x-ui.badge>
                    @endif
                </x-ui.action>

                <x-ui.popover state="filtersOpen" title="Filtros" id="health-body-filters">
                    <x-ui.select name="range" label="Periodo" :options="$ranges" :selected="$range" icon="bi-calendar-range" wire:model.live="range" />
                    <x-ui.select name="status" label="Estado" :options="$statuses" :selected="$status" icon="bi-activity" wire:model.live="status" />
                    <x-ui.multi-select name="types" label="Tipo" :options="$typeLabels" all-label="Todos los tipos" icon="bi-tag" />
                    <x-slot:actions>
                        <x-ui.action variant="text" wire:click="clearFilters">Limpiar</x-ui.action>
                        <x-ui.action variant="filled" x-on:click="filtersOpen = false">Aplicar</x-ui.action>
                    </x-slot:actions>
                </x-ui.popover>
            </div>
        </div>

        @if (count($activeFilters) > 0)
            <div class="health-applied-filters" role="group" aria-label="Filtros aplicados">
                @foreach ($activeFilters as $filter)
                    <span class="md-chip md-chip-input health-applied-chip" wire:key="body-filter-{{ $filter['key'] }}-{{ $filter['value'] }}">
                        <i class="bi {{ $filter['icon'] }}" aria-hidden="true"></i>
                        <span>{{ $filter['label'] }}</span>
                        <button type="button" class="md-btn-icon md-btn--sm health-applied-chip__remove"
                                wire:click="removeFilter(@js($filter['key']), @js($filter['value']))"
                                aria-label="Quitar filtro {{ $filter['label'] }}" title="Quitar filtro">
                            <i class="bi bi-x" aria-hidden="true"></i>
                        </button>
                    </span>
                @endforeach
                <button type="button" class="md-btn-text md-btn--sm health-clear-filters" wire:click="clearFilters">Limpiar filtros</button>
            </div>
        @endif

        <article class="health-card health-body-card" x-data="healthBodyMap" x-init="$watch('selected', value => $wire.selectZone(value))" @health-body-locate.window="locate($event.detail.zone)">
            <div data-body-levels-host>
                <script type="application/json" data-body-levels>@json($mapZones)</script>
            </div>

            <div class="health-body-card__toolbar">
                <div class="health-view-toggle" role="group" aria-label="Perspectiva del cuerpo">
                    <button type="button" :aria-pressed="(view === 'front').toString()" :data-hint="(hint === 'front').toString()" @click="setView('front')">Frente</button>
                    <button type="button" :aria-pressed="(view === 'back').toString()" :data-hint="(hint === 'back').toString()" @click="setView('back')">Espalda</button>
                </div>
                <span class="health-body-card__caption" x-text="view === 'front' ? 'Vista frontal' : 'Vista posterior'">Vista frontal</span>
                <div class="health-view-toggle health-view-toggle--quiet" role="group" aria-label="Modelo corporal">
                    <button type="button" :aria-pressed="(model === 'male').toString()" @click="setModel('male')">Hombre</button>
                    <button type="button" :aria-pressed="(model === 'female').toString()" @click="setModel('female')">Mujer</button>
                </div>
            </div>

            <div class="health-body-card__stage">
                <div wire:ignore>
                    <x-health.body-models />
                </div>

                <aside class="health-heat-legend" aria-label="Escala de frecuencia">
                    <p class="health-heat-legend__title">Frecuencia de eventos</p>
                    <ul>
                        @foreach ($legend as $level => $text)
                            <li><span class="health-heat-swatch health-heat-{{ $level }}" aria-hidden="true"></span>{{ $text }}</li>
                        @endforeach
                    </ul>
                    <p class="health-heat-legend__hint"><i class="bi bi-hand-index" aria-hidden="true"></i> Toca una zona para ver sus eventos</p>
                </aside>
            </div>

            <div class="health-body-detail" x-cloak x-show="detail" :class="{ 'is-empty': detail && detail.count === 0 }">
                <span class="health-body-detail__dot" :class="detail ? 'health-heat-' + detail.level : ''" aria-hidden="true"></span>
                <div class="health-body-detail__text" aria-live="polite">
                    <strong x-text="detail?.label"></strong>
                    <span x-text="detail ? (detail.count === 0 ? 'Sin eventos en el periodo seleccionado' : detail.count + (detail.count === 1 ? ' evento' : ' eventos') + ' · Último: ' + detail.last) : ''"></span>
                </div>
                <a class="md-btn-tonal" x-show="detail && detail.href" :href="detail?.href" wire:navigate>Ver en Registro <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
            </div>
            <p class="health-body-card__hint" x-show="!detail"><i class="bi bi-lightbulb" aria-hidden="true"></i> Selecciona una zona del cuerpo o de la lista para explorar sus eventos en el Registro con esa zona filtrada.</p>
        </article>
    </section>

    <x-slot:rail>
        @include('livewire.health.partials.guardian-rail')
        <section class="health-card health-zones" aria-labelledby="health-zones-title">
            <header class="health-card__head">
                <i class="bi bi-geo-alt" aria-hidden="true"></i>
                <h2 id="health-zones-title">Zonas registradas</h2>
                <span class="health-card__count">{{ $sorts[$sort] }}</span>
                <x-ui.menu label="Ordenar zonas" icon="bi-sort-down" size="sm">
                    @foreach ($sorts as $key => $label)
                        <x-ui.menu-item :icon="$key === $sort ? 'bi-check2' : null" wire:click="$set('sort', '{{ $key }}')">{{ $label }}</x-ui.menu-item>
                    @endforeach
                </x-ui.menu>
            </header>
            <div class="health-card__body">
                @if ($zones->isEmpty())
                    <div class="health-body-empty">
                        <i class="bi bi-person-standing" aria-hidden="true"></i>
                        <p>No hay eventos con zona del cuerpo en este periodo.</p>
                    </div>
                @else
                    <ul class="health-zone-list">
                        @foreach ($zones as $zone)
                            <li class="health-zone-row" data-zone="{{ $zone['key'] }}" wire:key="zone-{{ $zone['key'] }}">
                                <a class="health-zone-row__main" href="{{ $zone['href'] }}" wire:navigate aria-label="Ver {{ $zone['label'] }} en el Registro: {{ $events($zone['count']) }}">
                                    <span class="health-zone-row__icon health-heat-{{ $zone['level'] }}"><i class="bi {{ $zone['icon'] }}" aria-hidden="true"></i></span>
                                    <span class="health-zone-row__body"><strong>{{ $zone['label'] }}</strong><span>{{ $zone['last'] }}</span></span>
                                    <span class="health-zone-count health-heat-{{ $zone['level'] }}">{{ $events($zone['count']) }}</span>
                                </a>
                                <x-ui.menu label="Más opciones de {{ $zone['label'] }}" size="sm">
                                    @if ($zone['onMap'])
                                        <x-ui.menu-item icon="bi-crosshair" x-on:click="$dispatch('health-body-locate', { zone: @js($zone['key']) })">Ubicar en el mapa</x-ui.menu-item>
                                    @endif
                                    <x-ui.menu-item icon="bi-clock-history" :href="$zone['href']">Ver en Registro</x-ui.menu-item>
                                    <x-ui.menu-item icon="bi-plus-lg" :href="route('health', ['new_area' => $zone['key']])">Registrar evento en esta zona</x-ui.menu-item>
                                </x-ui.menu>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>
    </x-slot:rail>
</x-module-shell>
