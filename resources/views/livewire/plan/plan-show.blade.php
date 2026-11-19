<x-module-shell module="plans" :active-route="$activeRoute" :title="$plan->title" class="plans-page plans-page--detail"
                x-data x-init="$nextTick(() => {
                    const activeTab = $el.querySelector('.md-module-tab[aria-current=page]');
                    const track = activeTab?.parentElement;
                    if (!track) return;
                    const tabBounds = activeTab.getBoundingClientRect();
                    const trackBounds = track.getBoundingClientRect();
                    if (tabBounds.right > trackBounds.right || tabBounds.left < trackBounds.left) {
                        track.scrollLeft += tabBounds.left - trackBounds.left;
                    }
                })"
                :tabs="$detailTabs" :back="['href' => route('plans'), 'label' => 'Volver a Planes']">
    @php
        $visitAction = [
            'label' => 'Registrar visita',
            'icon' => 'bi-check2-circle',
            'action' => "openVisitForm('{$plan->id}')",
        ];

        $sectionActions = match ($section) {
            'tasks' => [
                ['label' => 'Crear tarea', 'icon' => 'bi-plus-lg', 'event' => 'task-editor', 'detail' => ['action' => 'openForm'], 'create' => true],
                ['label' => 'Vincular tareas', 'icon' => 'bi-link-45deg', 'action' => 'openTaskLinks', 'create' => true],
            ],
            'people' => [['label' => 'Asociar personas', 'icon' => 'bi-people', 'action' => "openPlanForm('{$plan->id}', 'people')", 'create' => true]],
            'links' => [['label' => 'Compartir enlace', 'icon' => 'bi-link-45deg', 'action' => "openPlanForm('{$plan->id}', 'links')", 'create' => true]],
            default => [],
        };
    @endphp

    <x-slot:actions>
        <x-module-actions :primary="$visitAction" :secondary="$sectionActions" />
    </x-slot:actions>

    @include('livewire.plan.partials.show-'.$section)

    <x-slot:rail>
        <section class="plan-card" aria-labelledby="plan-data-title">
            <header class="plan-card__head">
                <i class="bi {{ $plan->icon() }}" aria-hidden="true"></i>
                <h2 id="plan-data-title">Datos del plan</h2>
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
                    @if ($plan->country)<div><dt>País</dt><dd>{{ $plan->country->name }}</dd></div>@endif
                    @if ($plan->city)<div><dt>Ciudad</dt><dd>{{ $plan->catalogCity?->label() ?? $plan->city }}</dd></div>@endif
                    @if ($plan->scheduled_on)<div><dt>Fecha</dt><dd>{{ $plan->scheduleLabel() }}</dd></div>@endif
                    @if ($plan->address)
                        @php
                            $mapQuery = rawurlencode($plan->mapQuery());
                        @endphp
                        <div class="plan-data__full">
                            <dt>Dirección</dt>
                            <dd>
                                <x-ui.menu class="plan-address" :label="'Abrir dirección en mapas: '.$plan->address" :trigger-text="$plan->address" icon="bi-geo-alt" align="start">
                                    <x-ui.menu-item :href="'https://www.google.com/maps/search/?api=1&query='.$mapQuery" target="_blank" rel="noopener noreferrer">Abrir en Google Maps</x-ui.menu-item>
                                    <x-ui.menu-item :href="'https://waze.com/ul?utm_source=miso&q='.$mapQuery" target="_blank" rel="noopener noreferrer">Abrir en Waze</x-ui.menu-item>
                                </x-ui.menu>
                            </dd>
                        </div>
                    @endif
                    @if ($plan->circles->isNotEmpty())<div class="plan-data__full"><dt>Círculos</dt><dd>{{ $plan->circles->pluck('name')->implode(', ') }}</dd></div>@endif
                </dl>
            </div>
        </section>
    </x-slot:rail>

    @include('livewire.plan.partials.plan-dialogs')
</x-module-shell>
