{{-- Guardián de módulo (CHG-055): franja con el escenario de su tipo al inicio de una tarjeta del panel lateral. --}}
@props(['guardian', 'line'])

@if ($guardian)
    <div {{ $attributes->class(['game-guardian', 'game-stage--'.($guardian['stage'] ?? 'cielo'), $guardian['type_class']]) }}>
        <a href="{{ route('game.collection', ['species' => $guardian['species_id']]) }}" wire:navigate
           class="game-guardian__art" aria-label="Ver ficha de {{ $guardian['name'] }}">
            @if ($guardian['image'])
                <img src="{{ $guardian['image'] }}" width="96" height="96" loading="lazy" alt="">
            @else
                <span class="game-placeholder" aria-hidden="true"><i class="bi bi-stars"></i></span>
            @endif
        </a>
        <div class="game-guardian__talk">
            <span class="game-guardian__name">{{ $guardian['name'] }} · Nv {{ $guardian['level'] }}</span>
            <p class="game-guardian__bubble" aria-live="polite">“{{ $line }}”</p>
        </div>
    </div>
@endif
