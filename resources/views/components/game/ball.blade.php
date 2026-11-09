@props(['size' => 'sm'])

{{-- Ball del juego: CSS puro. Tamaños sm (28 px), md (56 px) y lg (96 px). Decorativa. --}}
<span {{ $attributes->class(['game-ball', 'game-ball--'.$size => $size !== 'sm']) }} aria-hidden="true"></span>
