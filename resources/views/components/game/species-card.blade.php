@props(['species', 'discovered' => false, 'image' => null, 'inTeam' => false])

@php
    $number = '#'.str_pad($species->number, 3, '0', STR_PAD_LEFT);
    $typeClass = \App\Services\Game\CompanionProgress::typeClass($species->types[0] ?? null);
    $label = $number.' '.($discovered ? $species->name.', '.config("game.rarity_labels.{$species->rarity}", $species->rarity) : 'Especie sin descubrir').($inTeam ? ', en tu equipo' : '');
@endphp

<button type="button" aria-label="{{ $label }}"
        {{ $attributes->class([
            'md-btn-text game-grid__item',
            $discovered ? 'is-discovered '.$typeClass.' game-rarity--'.$species->rarity : 'is-hidden',
        ]) }}>
    @if ($inTeam)<i class="bi bi-people-fill game-grid__team" aria-hidden="true"></i>@endif
    <span class="md-label-small game-muted">{{ $number }}</span>
    @if ($image)
        <img src="{{ $image }}" width="80" height="80" loading="lazy" alt="">
    @else
        <span class="game-placeholder" aria-hidden="true"><i class="bi {{ $discovered ? 'bi-stars' : 'bi-question-lg' }}"></i></span>
    @endif
    <span class="md-body-small">{{ $discovered ? $species->name : '?' }}</span>
    @if ($discovered)
        <x-game.rarity-tag :rarity="$species->rarity" />
    @endif
</button>
