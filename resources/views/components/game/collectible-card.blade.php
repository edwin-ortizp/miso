@props([
    'species',
    'level' => 1,
    'power' => null,
    'levelXp' => 0,
    'xpPerLevel' => 40,
    'image' => null,
    'ability' => null,
    'href' => null,
    'flip' => false,
])

@php
    // Carta coleccionable de diseño original de MiSo (no reproduce cartas comerciales).
    $types = $species->types ?? [];
    $typeClass = \App\Services\Game\CompanionProgress::typeClass($types[0] ?? null);
    $stats = collect(['hp' => 'PS', 'attack' => 'ATQ', 'speed' => 'VEL'])
        ->filter(fn ($label, $key) => isset($species->stats[$key]))
        ->map(fn ($label, $key) => ['label' => $label, 'value' => (int) $species->stats[$key]]);
    $label = collect([$species->name, 'nivel '.$level, $power !== null ? 'poder '.$power : null, $ability ? 'afinidad '.$ability : null])->filter()->implode(', ');
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" wire:navigate aria-label="{{ $label }}" @endif
    {{ $attributes->class([
        'game-collectible',
        $href ? 'md-btn-text' : null,
        $typeClass,
        'game-rarity--'.$species->rarity,
        'is-flipping' => $flip,
    ]) }}
    x-data
    x-on:pointermove="if (event.pointerType === 'mouse' && ! window.matchMedia('(prefers-reduced-motion: reduce)').matches) { const r = $el.getBoundingClientRect(); $el.style.setProperty('--game-tilt-y', (((event.clientX - r.left) / r.width - 0.5) * 12) + 'deg'); $el.style.setProperty('--game-tilt-x', ((0.5 - (event.clientY - r.top) / r.height) * 12) + 'deg') }"
    x-on:pointerleave="$el.style.setProperty('--game-tilt-x', '0deg'); $el.style.setProperty('--game-tilt-y', '0deg')">
    <span class="game-collectible__inner">
        <span class="game-collectible__head">
            <strong class="game-collectible__name">{{ $species->name }}</strong>
            <span class="game-collectible__level">Nv {{ $level }}</span>
        </span>
        <span class="game-collectible__meta">
            <span>#{{ str_pad($species->number, 3, '0', STR_PAD_LEFT) }}</span>
            <x-game.rarity-tag :rarity="$species->rarity" />
        </span>
        <span class="game-collectible__art">
            @if ($image)
                <img src="{{ $image }}" width="160" height="160" loading="lazy" alt="">
            @else
                <span class="game-placeholder" aria-hidden="true"><i class="bi bi-stars"></i></span>
            @endif
        </span>
        @if ($types)
            <span class="game-collectible__types">
                @foreach ($types as $type)
                    <span class="game-type-chip {{ \App\Services\Game\CompanionProgress::typeClass($type) }}">{{ $type }}</span>
                @endforeach
            </span>
        @endif
        @if ($stats->isNotEmpty())
            <span class="game-collectible__stats" aria-hidden="true">
                @foreach ($stats as $stat)
                    <span class="game-collectible__stat">
                        <span>{{ $stat['label'] }}</span>
                        <span class="game-stats__bar"><span style="--md-progress-value: {{ min(100, round($stat['value'] / 255 * 100)) }}%"></span></span>
                        <span>{{ $stat['value'] }}</span>
                    </span>
                @endforeach
            </span>
        @endif
        <span class="game-collectible__foot">
            <x-ui.progress :value="$levelXp" :max="$xpPerLevel" label="Experiencia" :value-text="$levelXp.' / '.$xpPerLevel.' xp'" />
            @if ($power !== null)
                <span class="game-power"><i class="bi bi-lightning-charge-fill" aria-hidden="true"></i>Poder {{ $power }}</span>
            @endif
            @if ($ability)
                <span class="md-label-medium"><i class="bi bi-diamond-fill" aria-hidden="true"></i> Afinidad {{ $ability }}</span>
            @endif
        </span>
    </span>
</{{ $tag }}>
