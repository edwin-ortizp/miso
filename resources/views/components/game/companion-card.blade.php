@props(['species', 'companion', 'ability', 'power', 'levelXp', 'xpPerLevel', 'image' => null, 'href'])

@php
    $types = $species->types ?? [];
    $typeClass = \App\Services\Game\CompanionProgress::typeClass($types[0] ?? null);
@endphp

<a href="{{ $href }}" wire:navigate
   aria-label="{{ $species->name }}, nivel {{ $companion->level }}, poder {{ $power }}, afinidad {{ $ability }}"
   {{ $attributes->class(['md-btn-text game-card', $typeClass, 'game-rarity--'.$species->rarity]) }}>
    <span class="game-card__band">
        <span>#{{ str_pad($species->number, 3, '0', STR_PAD_LEFT) }}</span>
        <x-game.rarity-tag :rarity="$species->rarity" />
    </span>
    <span class="game-card__art">
        @if ($image)
            <img src="{{ $image }}" width="120" height="120" loading="lazy" alt="">
        @else
            <span class="game-placeholder" aria-hidden="true"><i class="bi bi-stars"></i></span>
        @endif
    </span>
    <span class="game-card__body">
        <span class="game-card__title">
            <strong class="md-title-medium">{{ $species->name }}</strong>
            <span class="md-label-large">Nv {{ $companion->level }}</span>
        </span>
        <span class="game-power md-body-medium"><i class="bi bi-lightning-charge-fill" aria-hidden="true"></i>Poder {{ $power }}</span>
        <x-ui.progress :value="$levelXp" :max="$xpPerLevel" label="Experiencia" :value-text="$levelXp.' / '.$xpPerLevel.' xp'" />
        @if ($types)
            <span class="game-card__types">
                @foreach ($types as $type)
                    <span class="game-type-chip {{ \App\Services\Game\CompanionProgress::typeClass($type) }}">{{ $type }}</span>
                @endforeach
            </span>
        @endif
        <span class="md-label-medium"><i class="bi bi-diamond-fill" aria-hidden="true"></i> Afinidad {{ $ability }}</span>
    </span>
</a>
