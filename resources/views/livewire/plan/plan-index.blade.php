<x-module-shell module="plans" class="plans-page" :active-route="$scheduled ? 'plans.scheduled' : 'plans'" :navigation-query="['q'=>$q,'group'=>$group,'status'=>$status,'sort'=>$sort,'city'=>$city,'types'=>$types,'circles'=>$circles,'people'=>$people,'tags'=>$tags,'category'=>$category]">
    <x-slot:actions>
        <x-module-actions :primary="['label' => 'Agregar plan', 'icon' => 'bi-plus-lg', 'action' => 'openPlanForm']" :fab-always="true" />
    </x-slot:actions>

    <x-ui.management-card id="plans" :title="$scheduled ? 'Programados' : 'Tus planes'" icon="bi-map" :count="'('.$plans->total().' / '.$total.')'"
                          search="q" search-placeholder="Buscar planes" :active-filters="count($activeFilters)"
                          :paginator="$plans" noun="planes" class="plans-section"
                          sort-model="sort" :sort-options="$sorts" :sort-value="$sort">
        <x-slot:filters>
            <div class="md-chip-rail md-mcard__filter-chips" role="group" aria-label="Grupos de planes">
            <x-ui.chip variant="filter" icon="bi-grid" :selected="$group === ''" wire:click="setGroup('')">Todos ({{ $total }})</x-ui.chip>
            @foreach ($groups as $key => $item)
                <x-ui.chip variant="filter" :icon="$item['icon']" :selected="$group === $key" wire:click="setGroup('{{ $key }}')" wire:key="plans-group-{{ $key }}">{{ $item['label'] }} ({{ $item['count'] }})</x-ui.chip>
            @endforeach
            </div>
            @if ($scheduled)
                <x-ui.field name="from" type="date" label="Desde" wire:model.live="from" />
                <x-ui.field name="to" type="date" label="Hasta" wire:model.live="to" />
            @endif
            <x-ui.select name="category" label="Categoría" :options="$categoryOptions" :selected="$category" placeholder="Todas" wire:model.live="category" />
            @if (! $scheduled)
                    <x-ui.select name="status" label="Estado" :options="$statusOptions" :selected="$status" placeholder="Todos menos archivados" icon="bi-flag" wire:model.live="status" />
            @endif
                    <x-ui.select name="city" label="Ciudad" :options="$cityOptions" :selected="$city" placeholder="Todas las ciudades" icon="bi-geo-alt" wire:model.live="city" />
                    <x-ui.multi-select name="types" label="Tipo de plan" :options="$typeOptions" all-label="Todos los tipos" icon="bi-tag" />
                    <x-ui.multi-select name="circles" label="Círculo" :options="$circleOptions" all-label="Todos los círculos" icon="bi-diagram-3" />
                    <x-ui.multi-select name="people" label="Persona" :options="$peopleOptions" all-label="Todas las personas" icon="bi-people" />
                    <x-ui.multi-select name="tags" label="Etiquetas" :options="$tagOptions" all-label="Todas las etiquetas" icon="bi-tags" />
        </x-slot:filters>
        <x-slot:filterActions>
            <x-ui.action variant="outlined" icon="bi-eraser" wire:click="clearFilters">Limpiar</x-ui.action>
            <x-ui.action variant="filled" icon="bi-funnel-fill" x-on:click="filtersOpen = false">Filtrar</x-ui.action>
        </x-slot:filterActions>
        <x-slot:menu>
            <x-ui.menu-item icon="bi-shuffle" wire:click="surprise">Sorpréndeme</x-ui.menu-item>
        </x-slot:menu>
        @if (count($activeFilters) > 0)
            <x-slot:strip>
                <x-ui.applied-filters :filters="$activeFilters" />
            </x-slot:strip>
        @endif

        @if ($surpriseMessage)
            <p class="plans-notice" role="status"><i class="bi bi-info-circle" aria-hidden="true"></i> {{ $surpriseMessage }}</p>
        @endif

        @if ($dataState === \App\Support\Ui\DataState::CONTENT)
            <div class="plans-list">
                @foreach ($plans as $plan)
                    @include('livewire.plan.partials.plan-row', ['plan' => $plan, 'relationship' => null])
                @endforeach
            </div>
        @elseif ($dataState === \App\Support\Ui\DataState::FILTERED_EMPTY)
            <x-ui.state variant="filtered-empty" message="Prueba con otro grupo, otra búsqueda o limpia los filtros." />
        @else
            <x-ui.state variant="empty" icon="bi-map" title="Todavía no tienes planes" message="Guarda restaurantes, viajes o actividades que quieras hacer con tus personas." />
        @endif
    </x-ui.management-card>

    @if (! $scheduled)
    <x-slot:rail>
        <section class="plan-card" aria-labelledby="plans-summary-title">
            <header class="plan-card__head">
                <i class="bi bi-bar-chart-line" aria-hidden="true"></i>
                <h2 id="plans-summary-title">Resumen de planes</h2>
            </header>
            <div class="plan-card__body">
                <dl class="plans-kpis">
                    <div><dt>Pendientes</dt><dd>{{ $statusCounts['pending'] ?? 0 }}</dd></div>
                    <div><dt>Programados</dt><dd>{{ $statusCounts['scheduled'] ?? 0 }}</dd></div>
                    <div><dt>Realizados</dt><dd>{{ $statusCounts['done'] ?? 0 }}</dd></div>
                </dl>
            </div>
        </section>

        <section class="plan-card" aria-labelledby="plans-upcoming-title">
            <header class="plan-card__head">
                <i class="bi bi-calendar2-week" aria-hidden="true"></i>
                <h2 id="plans-upcoming-title">Próximos planes</h2>
            </header>
            <div class="plan-card__body">
                @forelse ($upcoming as $next)
                    @php $left = $next->daysUntil(); @endphp
                    <a class="plan-mini" href="{{ route('plans.show', $next) }}" wire:navigate>
                        <span class="plan-mini__icon"><i class="bi {{ $next->icon() }}" aria-hidden="true"></i></span>
                        <span class="plan-mini__body">
                            <strong>{{ $next->title }}</strong>
                            <span>{{ \Illuminate\Support\Str::ucfirst($next->scheduled_on->translatedFormat('D, j M Y')) }}</span>
                        </span>
                        <span class="plan-mini__badge">{{ $left === 0 ? 'Hoy' : $left.' '.($left === 1 ? 'día' : 'días') }}</span>
                    </a>
                @empty
                    <p class="plans-muted">Ningún plan tiene fecha próxima.</p>
                @endforelse
            </div>
        </section>

        <section class="plan-card" aria-labelledby="plans-stale-title">
            <header class="plan-card__head">
                <i class="bi bi-hourglass-split" aria-hidden="true"></i>
                <h2 id="plans-stale-title">Hace tiempo que no van</h2>
            </header>
            <div class="plan-card__body">
                @forelse ($stale as $old)
                    <a class="plan-mini" href="{{ route('plans.show', $old) }}" wire:navigate>
                        <span class="plan-mini__icon"><i class="bi {{ $old->icon() }}" aria-hidden="true"></i></span>
                        <span class="plan-mini__body">
                            <strong>{{ $old->title }}</strong>
                            <span>{{ \Illuminate\Support\Str::ucfirst($old->lastVisitLabel()) }} · {{ \App\Models\Plan::visitsLabel((int) $old->visits_count) }}</span>
                        </span>
                        <i class="bi bi-chevron-right plan-mini__trail" aria-hidden="true"></i>
                    </a>
                @empty
                    <p class="plans-muted">Cuando registres visitas verás aquí lo que llevan tiempo sin repetir.</p>
                @endforelse
            </div>
        </section>

        @include('livewire.plan.partials.plan-rail-links', ['links' => $recentLinks, 'id' => 'plans-links-title'])
    </x-slot:rail>
    @endif

    @include('livewire.plan.partials.plan-dialogs')
</x-module-shell>
