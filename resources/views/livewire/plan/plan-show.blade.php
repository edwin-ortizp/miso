<x-module-shell module="plans" active-route="plans.show" :title="$plan->title" class="plans-page" :back="['href' => route('plans'), 'label' => 'Volver a Planes']">
    <x-slot:actions>
        <x-module-actions :primary="['label' => 'Registrar visita', 'icon' => 'bi-check2-circle', 'action' => 'openVisitForm(\''.$plan->id.'\')']" :fab-always="true" />
    </x-slot:actions>

    @if ($plan->images->isNotEmpty())
        <div class="plan-gallery" role="group" aria-label="Imágenes de {{ $plan->title }}">
            @foreach ($plan->images as $image)
                <a class="plan-gallery__item {{ $loop->first ? 'is-main' : '' }}" href="{{ $image->url }}" target="_blank" rel="noopener noreferrer" wire:key="plan-image-{{ $image->id }}">
                    <i class="bi {{ $plan->icon() }}" aria-hidden="true"></i>
                    <img src="{{ $image->url }}" alt="{{ $loop->first ? 'Imagen principal de '.$plan->title : 'Imagen '.$loop->iteration.' de '.$plan->title }}" loading="lazy" referrerpolicy="no-referrer" x-data x-on:error="$el.remove()">
                </a>
            @endforeach
        </div>
    @endif

    <dl class="plan-metrics">
        <div><dt>Visitas</dt><dd>{{ (int) $plan->visits_count }}</dd></div>
        <div><dt>Última vez</dt><dd>{{ \Illuminate\Support\Str::ucfirst($plan->lastVisitLabel()) }}</dd></div>
        <div><dt>Personas</dt><dd>{{ $people->count() }}</dd></div>
    </dl>

    <section class="plan-card" aria-labelledby="plan-history-title">
        <header class="plan-card__head">
            <i class="bi bi-clock-history" aria-hidden="true"></i>
            <h2 id="plan-history-title">Historial</h2>
            <x-ui.action variant="tonal" icon="bi-plus-lg" size="sm" wire:click="openVisitForm('{{ $plan->id }}')">Registrar visita</x-ui.action>
        </header>
        <div class="plan-card__body">
            @forelse ($visits as $visit)
                <article class="plan-visit" wire:key="plan-visit-{{ $visit->id }}">
                    <span class="plan-visit__dot" aria-hidden="true"></span>
                    <div class="plan-visit__body">
                        <strong>{{ $visit->visited_on->translatedFormat('j M Y') }}</strong>
                        <span><i class="bi bi-people" aria-hidden="true"></i> {{ $visit->relationships->pluck('full_name')->implode(', ') }}</span>
                        @if ($visit->comment)
                            <p>{{ $visit->comment }}</p>
                        @endif
                    </div>
                    <x-ui.menu :label="'Más opciones de la visita del '.$visit->visited_on->translatedFormat('j M Y')">
                        <x-ui.menu-item icon="bi-trash" tone="danger" wire:click="deleteVisit('{{ $visit->id }}')" wire:confirm="¿Eliminar esta visita del historial?">Eliminar</x-ui.menu-item>
                    </x-ui.menu>
                </article>
            @empty
                <p class="plans-muted">Aún no lo han hecho. Cuando vayan, regístralo con fecha y con quién fuiste.</p>
            @endforelse
        </div>
    </section>

    <x-slot:rail>
        <section class="plan-card" aria-labelledby="plan-data-title">
            <header class="plan-card__head">
                <i class="bi {{ $plan->icon() }}" aria-hidden="true"></i>
                <h2 id="plan-data-title">Datos</h2>
                <x-ui.menu :label="'Más opciones de '.$plan->title">
                    <x-ui.menu-item icon="bi-pencil" wire:click="openPlanForm('{{ $plan->id }}')">Editar</x-ui.menu-item>
                    @if (in_array($plan->status, ['done', 'archived'], true))
                        <x-ui.menu-item icon="bi-arrow-counterclockwise" wire:click="setPlanStatus('{{ $plan->id }}', 'pending')">Volver a pendiente</x-ui.menu-item>
                    @else
                        <x-ui.menu-item icon="bi-check2-all" wire:click="setPlanStatus('{{ $plan->id }}', 'done')">Marcar como realizado</x-ui.menu-item>
                        <x-ui.menu-item icon="bi-archive" wire:click="setPlanStatus('{{ $plan->id }}', 'archived')">Archivar</x-ui.menu-item>
                    @endif
                    <x-ui.menu-divider />
                    <x-ui.menu-item icon="bi-trash" tone="danger" wire:click="deletePlan('{{ $plan->id }}')" wire:confirm="¿Eliminar este plan y todo su historial?">Eliminar</x-ui.menu-item>
                </x-ui.menu>
            </header>
            <div class="plan-card__body">
                <dl class="plan-data">
                    <div><dt>Estado</dt><dd><span class="plan-status plan-status--{{ $plan->status }}">{{ $plan->statusLabel() }}</span></dd></div>
                    <div><dt>Tipo</dt><dd>{{ $plan->typeLabel() }}</dd></div>
                    @if ($plan->tags->isNotEmpty())
                        <div class="plan-data__full"><dt>Etiquetas</dt><dd class="d-flex flex-wrap gap-2">
                            @foreach ($plan->tags as $tag)
                                <span class="md-chip"><i class="bi {{ $tag->icon }}" aria-hidden="true"></i> {{ $tag->name }}</span>
                            @endforeach
                        </dd></div>
                    @endif
                    @if ($plan->category || $plan->planCategory)<div><dt>Categoría</dt><dd><x-ui.catalog-chip tone="accent" :icon="$plan->planCategory?->icon ?: 'bi-tag'">{{ $plan->planCategory?->path_label ?: $plan->category }}</x-ui.catalog-chip></dd></div>@endif
                    @if ($plan->city)<div><dt>Ciudad</dt><dd>{{ $plan->city }}</dd></div>@endif
                    @if ($plan->scheduled_on)<div><dt>Fecha</dt><dd>{{ $plan->scheduled_on->translatedFormat('j M Y') }}@if ($plan->ends_on) – {{ $plan->ends_on->translatedFormat('j M Y') }}@endif</dd></div>@endif
                    @if ($plan->address)<div class="plan-data__full"><dt>Dirección</dt><dd>{{ $plan->address }}</dd></div>@endif
                    @if ($plan->circles->isNotEmpty())<div class="plan-data__full"><dt>Círculos</dt><dd>{{ $plan->circles->pluck('name')->implode(', ') }}</dd></div>@endif
                    @if ($plan->notes)<div class="plan-data__full"><dt>Notas</dt><dd>{{ $plan->notes }}</dd></div>@endif
                </dl>
            </div>
        </section>

        <section class="plan-card" aria-labelledby="plan-people-title">
            <header class="plan-card__head">
                <i class="bi bi-people" aria-hidden="true"></i>
                <h2 id="plan-people-title">Personas asociadas</h2>
            </header>
            <div class="plan-card__body">
                @forelse ($people as $row)
                    <a class="plan-mini" href="{{ route('relationships.plans', $row['person']) }}" wire:navigate>
                        <span class="plan-mini__icon plan-mini__icon--avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($row['person']->full_name, 0, 1)) }}</span>
                        <span class="plan-mini__body">
                            <strong>{{ $row['person']->full_name }}</strong>
                            <span>{{ \App\Models\Plan::visitsLabel($row['count']) }}@if ($row['last']) · última {{ $row['last']->locale('es')->diffForHumans(['parts' => 1]) }}@endif</span>
                        </span>
                        <i class="bi bi-chevron-right plan-mini__trail" aria-hidden="true"></i>
                    </a>
                @empty
                    <p class="plans-muted">Aún no está asociado a nadie.</p>
                @endforelse
            </div>
        </section>

        <section class="plan-card" aria-labelledby="plan-links-title">
            <header class="plan-card__head">
                <i class="bi bi-link-45deg" aria-hidden="true"></i>
                <h2 id="plan-links-title">Enlaces</h2>
            </header>
            <div class="plan-card__body">
                @forelse ($plan->links as $link)
                    @php $platform = $link->platform(); @endphp
                    <a class="plan-mini" href="{{ $link->url }}" target="_blank" rel="noopener noreferrer">
                        <span class="plan-mini__icon"><i class="bi {{ $platform['icon'] }}" aria-hidden="true"></i></span>
                        <span class="plan-mini__body">
                            <strong>{{ $link->label ?: $platform['label'] }}</strong>
                            <span>{{ $platform['label'] }}</span>
                        </span>
                        <i class="bi bi-box-arrow-up-right plan-mini__trail" aria-hidden="true"></i>
                    </a>
                @empty
                    <p class="plans-muted">Sin enlaces.</p>
                @endforelse
                <div>
                    <x-ui.action variant="outlined" icon="bi-link-45deg" wire:click="openPlanForm('{{ $plan->id }}')">Compartir enlace</x-ui.action>
                </div>
            </div>
        </section>
    </x-slot:rail>

    @include('livewire.plan.partials.plan-dialogs')
</x-module-shell>
