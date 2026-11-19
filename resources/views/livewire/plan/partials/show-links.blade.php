<section class="plan-card" aria-labelledby="plan-links-title">
    <header class="plan-card__head">
        <i class="bi bi-link-45deg" aria-hidden="true"></i>
        <h2 id="plan-links-title">Enlaces</h2>
        <x-ui.menu label="Más opciones de enlaces">
            <x-ui.menu-item icon="bi-pencil" wire:click="openPlanForm('{{ $plan->id }}', 'links')">Gestionar enlaces</x-ui.menu-item>
        </x-ui.menu>
    </header>
    <div class="plan-card__body">
        @forelse ($plan->links as $link)
            @php
                $platform = $link->platform();
            @endphp
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
            <x-ui.action variant="outlined" icon="bi-link-45deg" wire:click="openPlanForm('{{ $plan->id }}', 'links')">Compartir enlace</x-ui.action>
        @endforelse
    </div>
</section>
