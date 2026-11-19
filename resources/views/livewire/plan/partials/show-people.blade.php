<section class="plan-card" aria-labelledby="plan-people-title">
    <header class="plan-card__head">
        <i class="bi bi-people" aria-hidden="true"></i>
        <h2 id="plan-people-title">Personas asociadas</h2>
        <x-ui.menu label="Más opciones de personas">
            <x-ui.menu-item icon="bi-pencil" wire:click="openPlanForm('{{ $plan->id }}', 'people')">Gestionar personas</x-ui.menu-item>
        </x-ui.menu>
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
            <x-ui.action variant="outlined" icon="bi-people" wire:click="openPlanForm('{{ $plan->id }}', 'people')">Asociar personas</x-ui.action>
        @endforelse
    </div>
</section>
