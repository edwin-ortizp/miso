{{-- Camino de temporada (CHG-079): 7 paradas, puntos y siguiente recompensa. Congelado al terminar. --}}
@props(['path'])
@php
    $current = $path['next']['n'] ?? null;
@endphp
<section {{ $attributes->class(['game-path']) }} aria-labelledby="game-path-title">
    <div class="d-flex justify-content-between gap-2 flex-wrap align-items-baseline">
        <h3 class="md-title-medium mb-0" id="game-path-title">Camino de la temporada</h3>
        <span class="md-label-large">{{ $path['points'] }} de {{ $path['target'] }} puntos</span>
    </div>

    <x-ui.progress :value="min($path['points'], $path['target'])" :max="$path['target']" tone="warning" label="Puntos del camino" />

    <ol class="game-path__stops">
        @foreach ($path['nodes'] as $node)
            @php
                $state = $node['reached'] ? 'reached' : ($node['n'] === $current && ! $path['frozen'] ? 'current' : 'locked');
                $missing = max(0, $node['at'] - $path['points']);
                $text = $node['reached']
                    ? "Parada {$node['n']} · {$node['label']}, alcanzada"
                    : "Parada {$node['n']} · {$node['label']}, faltan {$missing} puntos";
            @endphp
            <li class="game-path__stop game-path__stop--{{ $state }}" @if ($state === 'current') aria-current="step" @endif>
                <span class="game-path__dot" aria-hidden="true">
                    <i class="bi {{ $node['reached'] ? 'bi-check-lg' : ($node['reward']['type'] === 'xp' ? 'bi-stars' : 'bi-circle-half') }}"></i>
                </span>
                <span class="game-path__n" aria-hidden="true">{{ $node['n'] }}</span>
                <span class="game-path__reward" aria-hidden="true">{{ $node['label'] }}</span>
                <span class="visually-hidden">{{ $text }}</span>
            </li>
        @endforeach
    </ol>

    @if ($path['frozen'])
        <p class="md-body-medium mb-0">Temporada terminada. Lo ganado queda contigo.</p>
    @elseif ($path['next'])
        <p class="md-body-medium mb-0">
            @if ($path['points'] === 0)
                Haz tu primera actividad para avanzar.
            @else
                Siguiente: parada {{ $path['next']['n'] }} · {{ $path['next']['label'] }}
            @endif
        </p>
    @endif
    <p class="md-body-small game-muted mb-0">Cada actividad suma 1 (hasta 5 al día); cada misión cumplida, 3.</p>
</section>
