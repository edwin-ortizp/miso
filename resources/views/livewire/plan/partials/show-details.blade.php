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

@if ($plan->notes || $plan->images->isEmpty())
    <section class="plan-card" aria-labelledby="plan-notes-title">
        <header class="plan-card__head">
            <i class="bi bi-card-text" aria-hidden="true"></i>
            <h2 id="plan-notes-title">Notas</h2>
        </header>
        <div class="plan-card__body">
            @if ($plan->notes)
                <p class="plan-notes">{{ $plan->notes }}</p>
            @else
                <p class="plans-muted">Aún no hay imágenes ni notas.</p>
                <x-ui.action variant="outlined" icon="bi-pencil" wire:click="openPlanForm('{{ $plan->id }}')">Editar plan</x-ui.action>
            @endif
        </div>
    </section>
@endif
