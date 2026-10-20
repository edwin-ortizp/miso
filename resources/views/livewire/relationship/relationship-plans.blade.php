<x-module-shell module="relationships" :title="$relationship->full_name" class="plans-page"
                :tabs="\App\Support\Ui\Tabs\PersonTabs::for($relationship)" :back="\App\Support\Ui\Tabs\PersonTabs::back()">
    <x-slot:actions>
        <x-module-actions :primary="['label' => 'Agregar plan', 'icon' => 'bi-plus-lg', 'action' => 'openPlanForm']" :fab-always="true" />
    </x-slot:actions>

    <x-ui.management-card id="relationship-plans" :title="'Planes con '.$firstName" icon="bi-map" :count="'('.$plans->total().' / '.$total.')'"
                          :active-filters="$status !== '' ? 1 : 0" :paginator="$plans" noun="planes" class="plans-section">
        <x-slot:filters>
            <div class="md-chip-rail md-mcard__filter-chips" role="group" aria-label="Estado de los planes">
            @foreach ($statusFilters as $key => $filter)
                <x-ui.chip variant="filter" :icon="$filter['icon']" :selected="$status === $key" wire:click="setStatus('{{ $key }}')" wire:key="relationship-plans-status-{{ $key ?: 'all' }}">
                    {{ $filter['label'] }} ({{ $key === '' ? $total : ($counts[$key] ?? 0) }})
                </x-ui.chip>
            @endforeach
            </div>
        </x-slot:filters>
        @php
            $appliedChips = array_values(array_filter([
                $status !== '' ? ['key' => 'status', 'value' => null, 'label' => 'Estado: '.(\App\Livewire\Relationship\RelationshipPlans::STATUS_FILTERS[$status]['label'] ?? $status), 'icon' => 'bi-flag'] : null,
            ]));
        @endphp
        @if ($appliedChips !== [])
            <x-slot:strip>
                <x-ui.applied-filters :filters="$appliedChips" />
            </x-slot:strip>
        @endif
        <x-slot:menu>
            <x-ui.menu-item icon="bi-shuffle" wire:click="surprise">Sorpréndeme</x-ui.menu-item>
            <x-ui.menu-item icon="bi-sliders" :href="route('plans', ['people' => [$relationship->id]])">Filtros avanzados en Planes</x-ui.menu-item>
        </x-slot:menu>

        @if ($surpriseMessage)
            <p class="plans-notice" role="status"><i class="bi bi-info-circle" aria-hidden="true"></i> {{ $surpriseMessage }}</p>
        @endif

        @if ($plans->isNotEmpty())
            <div class="plans-list">
                @foreach ($plans as $plan)
                    @include('livewire.plan.partials.plan-row', ['plan' => $plan, 'relationship' => $relationship])
                @endforeach
            </div>
        @elseif ($total > 0)
            <x-ui.state variant="filtered-empty" message="No hay planes con este estado." />
        @else
            <x-ui.state variant="empty" icon="bi-map" title="Aún no tienen planes" message="Agrega un lugar o actividad para hacer juntos, o asocia uno que ya tengas." />
        @endif
    </x-ui.management-card>

    <x-slot:rail>
        <section class="plan-card" aria-labelledby="relationship-plans-summary">
            <header class="plan-card__head">
                <i class="bi bi-bar-chart-line" aria-hidden="true"></i>
                <h2 id="relationship-plans-summary">Resumen de planes</h2>
            </header>
            <div class="plan-card__body">
                <dl class="plans-kpis">
                    <div><dt>Pendientes</dt><dd>{{ $counts['pending'] ?? 0 }}</dd></div>
                    <div><dt>Programados</dt><dd>{{ $counts['scheduled'] ?? 0 }}</dd></div>
                    <div><dt>Realizados</dt><dd>{{ $counts['done'] ?? 0 }}</dd></div>
                </dl>
            </div>
        </section>

        <section class="plan-card" aria-labelledby="relationship-plans-upcoming">
            <header class="plan-card__head">
                <i class="bi bi-calendar2-week" aria-hidden="true"></i>
                <h2 id="relationship-plans-upcoming">Próximos planes</h2>
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
                    <p class="plans-muted">Ningún plan con fecha próxima.</p>
                @endforelse
            </div>
        </section>

        <section class="plan-card" aria-labelledby="relationship-plans-recent">
            <header class="plan-card__head">
                <i class="bi bi-clock-history" aria-hidden="true"></i>
                <h2 id="relationship-plans-recent">Últimas actividades</h2>
            </header>
            <div class="plan-card__body">
                @forelse ($recentVisits as $visit)
                    <a class="plan-mini" href="{{ route('plans.show', $visit->plan_id) }}" wire:navigate>
                        <span class="plan-mini__icon"><i class="bi {{ $visit->plan->icon() }}" aria-hidden="true"></i></span>
                        <span class="plan-mini__body">
                            <strong>{{ $visit->plan->title }}</strong>
                            <span>{{ $visit->visited_on->translatedFormat('j M Y') }}@if ($visit->comment) · {{ \Illuminate\Support\Str::limit($visit->comment, 40) }}@endif</span>
                        </span>
                    </a>
                @empty
                    <p class="plans-muted">Todavía no han registrado planes juntos.</p>
                @endforelse
            </div>
        </section>

        @include('livewire.plan.partials.plan-rail-links', ['links' => $recentLinks, 'id' => 'relationship-plans-links'])
    </x-slot:rail>

    @include('livewire.plan.partials.plan-dialogs')
</x-module-shell>
