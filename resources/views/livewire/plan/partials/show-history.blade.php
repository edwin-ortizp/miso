<section class="plan-card" aria-labelledby="plan-history-title">
    <header class="plan-card__head">
        <i class="bi bi-clock-history" aria-hidden="true"></i>
        <h2 id="plan-history-title">Historial</h2>
    </header>
    <div class="plan-card__body">
        @if ($visits->isNotEmpty())
            <p class="plans-muted">Última visita · {{ $plan->lastVisitLabel() }}</p>
        @endif
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
            <p class="plans-muted">Aún no hay visitas registradas.</p>
            <x-ui.action variant="outlined" icon="bi-check2-circle" wire:click="openVisitForm('{{ $plan->id }}')">Registrar visita</x-ui.action>
        @endforelse
    </div>
</section>
