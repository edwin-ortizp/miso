{{-- Reto del día (CHG-077): texto, avance y acción. En el Inicio el check-in abre la hoja sin salir. --}}
@props(['challenge', 'inHome' => false])
@php $left = max(0, $challenge['target'] - $challenge['done']); @endphp
<div {{ $attributes->class(['game-challenge']) }} aria-live="polite">
    <div>
        <p class="game-challenge__label">Reto de hoy · Uno al día</p>
        <p class="game-challenge__text" title="{{ $challenge['text'] }}">{{ $challenge['text'] }}</p>
    </div>
    @if ($challenge['completed'])
        <p class="game-challenge__done"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Cumplido. Mañana hay otro.</p>
    @else
        @if ($challenge['checkin'] && $inHome)
            <button type="button" class="md-btn-text" wire:click="$set('checkin', true)">Ir <i class="bi bi-arrow-right" aria-hidden="true"></i></button>
        @elseif ($challenge['url'])
            <a href="{{ $challenge['url'] }}" wire:navigate class="md-btn-text">Ir <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
        @endif
        <x-ui.progress class="game-challenge__progress" :value="$challenge['done']" :max="$challenge['target']" label="Avance del reto"
                       :value-text="$challenge['done'].' de '.$challenge['target'].' · Te falta '.$left" />
    @endif
</div>
