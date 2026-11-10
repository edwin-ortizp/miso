{{-- Integrante del día (CHG-043): ejemplar al azar con una frase.
     variant="wall": 5.ª columna de Próximos días en el panel; sin estado vacío.
     variant="card": tarjeta del dashboard, con invitación si aún no hay ejemplares. --}}
@props(['buddy', 'variant' => 'card', 'mood' => null, 'balls' => 0])

@if ($buddy && $variant === 'wall')
    @unless ($buddy['empty'])
        {{-- CHG-049: escenario por familia de tipo, reacción al día, balls por abrir y "Otra frase". --}}
        <article {{ $attributes->class(['wall-day', 'game-buddy-stage', 'game-stage--'.($buddy['stage'] ?? 'cielo'), 'is-'.($mood['key'] ?? 'greet'), $buddy['type_class']]) }} aria-label="Compañero del día">
            <span class="game-buddy-stage__scene" aria-hidden="true"></span>
            <header class="game-buddy-stage__head">
                <span>Compañero</span>
                @if ($mood)
                    <strong class="game-buddy-stage__mood">{{ $mood['text'] }}</strong>
                @endif
            </header>
            <a href="{{ route('game.collection', ['species' => $buddy['species_id']]) }}" wire:navigate
               class="game-buddy-stage__body" aria-label="Ver ficha de {{ $buddy['name'] }}">
                <span class="game-buddy-stage__art">
                    @if ($buddy['image'])
                        <img src="{{ $buddy['image'] }}" width="160" height="160" loading="lazy" alt="">
                    @else
                        <span class="game-placeholder" aria-hidden="true"><i class="bi bi-stars"></i></span>
                    @endif
                </span>
                <span class="game-buddy-stage__name">{{ $buddy['name'] }} · Nv {{ $buddy['level'] }}</span>
            </a>
            <p class="game-buddy-stage__quote">“{{ $buddy['quote'] }}”</p>
            <footer class="game-buddy-stage__actions">
                <x-ui.action variant="text" size="sm" icon="bi-arrow-repeat" class="game-buddy-stage__btn"
                             wire:click="anotherQuote" wire:loading.attr="disabled" wire:target="anotherQuote">Otra frase</x-ui.action>
                @if ($balls > 0)
                    <a href="{{ route('game.balls') }}" wire:navigate class="game-buddy-stage__btn is-balls">
                        <x-game.ball /> Tienes {{ $balls }} {{ $balls === 1 ? 'ball' : 'balls' }}
                    </a>
                @endif
            </footer>
        </article>
    @endunless
@elseif ($buddy)
    @if ($buddy['empty'])
        <div {{ $attributes->class(['game-buddy', 'is-empty']) }}>
            <x-game.ball class="game-buddy__ball" />
            <p class="game-buddy__quote mb-0">Abre tu primera ball para conocer a tu compañero.</p>
            <a href="{{ route('game.balls') }}" wire:navigate class="md-btn-text game-buddy__action">Ir a Balls</a>
        </div>
    @else
        <a href="{{ route('game.collection', ['species' => $buddy['species_id']]) }}" wire:navigate
           aria-label="Ver ficha de {{ $buddy['name'] }}"
           {{ $attributes->class(['game-buddy', $buddy['type_class']]) }}>
            <span class="game-buddy__art">
                @if ($buddy['image'])
                    <img src="{{ $buddy['image'] }}" width="96" height="96" loading="lazy" alt="">
                @else
                    <span class="game-placeholder" aria-hidden="true"><i class="bi bi-stars"></i></span>
                @endif
            </span>
            <span class="game-buddy__body">
                <span class="game-buddy__name">{{ $buddy['name'] }} · Nv {{ $buddy['level'] }}</span>
                <span class="game-buddy__quote">“{{ $buddy['quote'] }}”</span>
            </span>
        </a>
    @endif
@endif
