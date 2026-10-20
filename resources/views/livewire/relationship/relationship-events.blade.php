<x-module-shell module="relationships" title="Acontecimientos" subtitle="Lo que ya pasó y lo que viene con las personas que te importan.">
    <div class="md-summary-strip mb-3" aria-label="Resumen de acontecimientos">
        <span class="md-count-badge--info">{{ $upcomingCount }} próximos</span>
        @if ($sensitiveHidden)
            <span class="md-count-badge--primary">{{ $sensitiveHidden }} sensibles ocultos</span>
        @endif
    </div>

    @php
        $eventFilterCount = collect([$showArchived, $includeSensitive, $relationFilter, $categoryFilter])->filter()->count();
    @endphp
    <nav aria-label="Ajustes de categorías"><x-ui.action variant="text" icon="bi-diagram-3" :href="route('categories.settings', 'relationship-events')">Gestionar categorías</x-ui.action></nav>
    <x-ui.management-card id="relationship-events" title="Acontecimientos" icon="bi-calendar-event" :count="'('.$events->total().')'"
                          search="search" search-placeholder="Buscar acontecimientos" :active-filters="$eventFilterCount"
                          :paginator="$events" noun="acontecimientos" alpine="openMenu: null">
        <x-slot:filters>
            <div class="md-chip-rail md-mcard__filter-chips" role="group" aria-label="Filtros de acontecimientos" @click.outside="openMenu = null">
            @foreach ($periods as $key => $label)
                <button wire:click="$set('periodFilter', '{{ $key }}')"
                        class="md-chip md-chip-filter {{ $periodFilter === $key ? 'selected' : '' }}">{{ $label }}</button>
            @endforeach

            <div class="md-chip-rail__divider"></div>

            <button wire:click="$toggle('showArchived')"
                    class="md-chip md-chip-filter {{ $showArchived ? 'selected' : '' }}">
                <i class="bi bi-archive"></i> Archivados
            </button>

            <button wire:click="$toggle('includeSensitive')"
                    class="md-chip md-chip-filter {{ $includeSensitive ? 'selected' : '' }}"
                    title="Solo durante esta consulta">
                <i class="bi {{ $includeSensitive ? 'bi-eye' : 'bi-eye-slash' }}"></i> Incluir sensibles
            </button>

            <div class="md-chip-rail__divider"></div>

            <div class="md-chip-menu" :class="{ 'open': openMenu === 'relation' }">
                <button @click="openMenu = openMenu === 'relation' ? null : 'relation'"
                        class="md-chip md-chip-filter {{ $relationFilter ? 'selected' : '' }}">
                    {{ $relationFilter ? $relationships->firstWhere('id', $relationFilter)?->full_name : 'Relación' }}
                    <i class="bi bi-chevron-down md-chip-menu__arrow"></i>
                </button>
                <div x-show="openMenu === 'relation'" x-transition x-cloak class="md-chip-menu__dropdown">
                    <button wire:click="$set('relationFilter', '')" @click="openMenu = null"
                            class="md-chip-menu__item {{ $relationFilter === '' ? 'active' : '' }}">Todas</button>
                    @foreach ($relationships as $relation)
                        <button wire:click="$set('relationFilter', '{{ $relation->id }}')" @click="openMenu = null"
                                class="md-chip-menu__item {{ $relationFilter === $relation->id ? 'active' : '' }}">{{ $relation->full_name }}</button>
                    @endforeach
                </div>
            </div>

            <div class="md-chip-menu" :class="{ 'open': openMenu === 'category' }">
                <button @click="openMenu = openMenu === 'category' ? null : 'category'"
                        class="md-chip md-chip-filter {{ $categoryFilter ? 'selected' : '' }}">
                    {{ $categoryFilter ? \App\Support\CategoryCatalog::options('relationship-events')[$categoryFilter] : 'Categoría' }}
                    <i class="bi bi-chevron-down md-chip-menu__arrow"></i>
                </button>
                <div x-show="openMenu === 'category'" x-transition x-cloak class="md-chip-menu__dropdown">
                    <button wire:click="$set('categoryFilter', '')" @click="openMenu = null"
                            class="md-chip-menu__item {{ $categoryFilter === '' ? 'active' : '' }}">Todas</button>
                    @foreach (\App\Support\CategoryCatalog::options('relationship-events') as $key => $label)
                        <button wire:click="$set('categoryFilter', '{{ $key }}')" @click="openMenu = null"
                                class="md-chip-menu__item {{ $categoryFilter === $key ? 'active' : '' }}">{{ $label }}</button>
                    @endforeach
                </div>
            </div>
            </div>
        </x-slot:filters>
        @php
            $appliedChips = array_values(array_filter([
                $periodFilter !== 'all' ? ['key' => 'periodFilter', 'value' => null, 'label' => 'Periodo: '.($periods[$periodFilter] ?? $periodFilter), 'icon' => 'bi-calendar-range'] : null,
                $showArchived ? ['key' => 'showArchived', 'value' => null, 'label' => 'Archivados', 'icon' => 'bi-archive'] : null,
                $includeSensitive ? ['key' => 'includeSensitive', 'value' => null, 'label' => 'Incluye sensibles', 'icon' => 'bi-eye'] : null,
                $relationFilter !== '' ? ['key' => 'relationFilter', 'value' => null, 'label' => 'Relación: '.($relationships->firstWhere('id', $relationFilter)?->full_name ?? '—'), 'icon' => 'bi-person'] : null,
                $categoryFilter !== '' ? ['key' => 'categoryFilter', 'value' => null, 'label' => 'Categoría: '.(\App\Support\CategoryCatalog::options('relationship-events')[$categoryFilter] ?? $categoryFilter), 'icon' => 'bi-tag'] : null,
            ]));
        @endphp
        @if ($appliedChips !== [])
            <x-slot:strip>
                <x-ui.applied-filters :filters="$appliedChips" />
            </x-slot:strip>
        @endif

    @forelse ($events as $event)
        <div class="md-card-outlined md-relationship-event mb-2" wire:key="global-event-{{ $event->id }}">
            <div class="md-relationship-event__body">
                <div class="md-title-small">
                    <a href="{{ route('relationships.show', $event->relationship_id) }}" wire:navigate>{{ $event->title }}</a>
                    @if ($event->is_sensitive)
                        <span class="md-chip-tonal md-chip-tonal--warning"><i class="bi bi-eye-slash" style="font-size: 0.5625rem;"></i> Sensible</span>
                    @endif
                </div>
                <div class="d-flex flex-wrap gap-1 mt-1 align-items-center">
                    <span class="md-chip-tonal md-chip-tonal--info">{{ $event->categoryLabel() }}</span>
                    <span class="md-chip-tonal">{{ $event->relationship?->full_name }}</span>
                    <span class="md-label-small" style="color: var(--md-sys-color-on-surface-variant);">{{ $event->dateLabel() }}</span>
                </div>
                @if ($event->notes)
                    <p class="md-body-small mt-1 mb-0" style="color: var(--md-sys-color-on-surface-variant);">{{ $event->notes }}</p>
                @endif
            </div>
            <a href="{{ route('relationships.show', $event->relationship_id) }}" class="md-btn-icon" title="Abrir relación" wire:navigate>
                <i class="bi bi-arrow-right"></i>
            </a>
        </div>
    @empty
        <x-empty-state icon="bi-calendar-event" title="Sin acontecimientos"
                       message="Registra acontecimientos desde el detalle de cada relación." />
    @endforelse
    </x-ui.management-card>

    <x-slot:rail>
        <x-context-widget title="Resumen" icon="bi-calendar-event" tone="success">
            <dl class="md-context-list">
                <div><dt>En esta vista</dt><dd>{{ $events->total() }}</dd></div>
                <div><dt>Próximos</dt><dd>{{ $upcomingCount }}</dd></div>
                <div><dt>Sensibles ocultos</dt><dd>{{ $sensitiveHidden }}</dd></div>
            </dl>
        </x-context-widget>

        <x-context-widget title="Privacidad" icon="bi-eye-slash">
            <p class="md-body-small mb-0">
                Los acontecimientos marcados como sensibles quedan fuera de esta vista y de los resúmenes.
                Siempre puedes verlos en el detalle de la relación.
            </p>
        </x-context-widget>

        <x-context-widget title="Enlaces" icon="bi-signpost-split">
            <div class="md-context-links">
                <a href="{{ route('relationships') }}" wire:navigate><i class="bi bi-people"></i> Relaciones</a>
                <a href="{{ route('relationships.birthdays') }}" wire:navigate><i class="bi bi-cake2"></i> Cumpleaños</a>
            </div>
        </x-context-widget>
    </x-slot:rail>
</x-module-shell>
