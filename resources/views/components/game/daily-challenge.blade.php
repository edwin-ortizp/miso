{{-- Reto del día (CHG-077). En el hero del Inicio es un mosaico de «Tu día» (CHG-081); en Balls, un bloque. --}}
@props(['challenge', 'inHome' => false])
@php $left = max(0, $challenge['target'] - $challenge['done']); @endphp
@if (! $inHome)
    @php
        $destination = match (true) {
            $challenge['checkin'] => 'Inicio',
            ($challenge['url'] ?? null) === route('water.daily') => 'Hidratación',
            ($challenge['url'] ?? null) === route('habits') => 'Hábitos',
            default => 'Tareas',
        };
    @endphp
    <article {{ $attributes->class(['game-challenge-quest', 'game-adventure-panel', 'game-challenge-quest--done' => $challenge['completed']]) }} aria-labelledby="game-day-challenge-balls" aria-live="polite">
        <div class="game-challenge-quest__head">
            <span class="game-challenge-quest__emblem" aria-hidden="true"><i class="bi {{ $challenge['completed'] ? 'bi-patch-check-fill' : 'bi-bullseye' }}"></i></span>
            <div><h2 id="game-day-challenge-balls" class="md-title-medium mb-0">Reto de hoy</h2><p class="game-adventure-panel__eyebrow">Tu misión del día</p></div>
            @if ($challenge['completed'])<span class="game-challenge-quest__seal"><i class="bi bi-stars" aria-hidden="true"></i> Cumplido</span>@endif
        </div>
        <p class="game-challenge-quest__objective">{{ $challenge['text'] }}</p>
        <x-ui.progress :value="$challenge['done']" :max="$challenge['target']" label="Avance del reto" :value-text="$challenge['done'].' de '.$challenge['target']" />
        @if ($challenge['completed'])
            <p class="game-challenge-quest__success"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Cumplido. Mañana hay otro.</p>
        @elseif ($challenge['url'])
            <x-ui.action :href="$challenge['url']" wire:navigate class="game-challenge-quest__go" trailing-icon="bi-arrow-right">Ir a {{ $destination }}</x-ui.action>
        @endif
    </article>
@else
<article {{ $attributes->class(['game-day-tile' => $inHome, 'game-challenge' => ! $inHome, 'game-day-tile--done' => $challenge['completed']]) }}
         aria-labelledby="game-day-challenge-{{ $inHome ? 'home' : 'balls' }}" aria-live="polite" x-data="{ infoOpen: false }">
    <x-game.day-tile-info id="game-day-challenge-info" title="Cómo funciona el reto del día">
        Cada día hay un reto distinto según lo que tienes pendiente. Si lo cumples, ganas una ball extra. Es aparte de la racha, aunque lo que hagas también suma a la racha.
    </x-game.day-tile-info>
    <p class="{{ $inHome ? 'game-day-tile__title' : 'game-challenge__label' }} md-label-large" id="game-day-challenge-{{ $inHome ? 'home' : 'balls' }}">
        <i class="bi bi-bullseye" aria-hidden="true"></i> Reto de hoy
    </p>
    <p class="{{ $inHome ? 'game-day-tile__value md-title-medium' : 'game-challenge__text md-title-small' }}" title="{{ $challenge['text'] }}">{{ $challenge['text'] }}</p>
    @if ($challenge['completed'])
        <p class="game-day-tile__text md-body-medium"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Cumplido. Mañana hay otro.</p>
    @else
        <div class="game-day-tile__row">
            <x-ui.progress class="game-day-tile__bar" :value="$challenge['done']" :max="$challenge['target']" label="Avance del reto"
                           :value-text="$challenge['done'].' de '.$challenge['target'].' · te falta '.$left" />
            @if ($challenge['checkin'] && $inHome)
                <button type="button" class="md-btn-text game-day-tile__go" wire:click="$set('checkin', true)">Ir <i class="bi bi-arrow-right" aria-hidden="true"></i></button>
            @elseif ($challenge['url'])
                <a href="{{ $challenge['url'] }}" wire:navigate class="md-btn-text game-day-tile__go">Ir <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
            @endif
        </div>
    @endif
</article>

@endif
