@props(['rarity' => 'common'])

@php
    $icons = ['common' => 'bi-circle', 'uncommon' => 'bi-diamond', 'rare' => 'bi-star-fill', 'legendary' => 'bi-gem'];
@endphp

{{-- La rareza siempre lleva texto e ícono, nunca solo color. --}}
<span {{ $attributes->class(['game-rarity-tag', 'game-rarity--'.$rarity]) }}>
    <i class="bi {{ $icons[$rarity] ?? 'bi-circle' }}" aria-hidden="true"></i>{{ config("game.rarity_labels.{$rarity}", $rarity) }}
</span>
