{{-- Integrante del día (CHG-043): ejemplar al azar con una frase.
     variant="wall": 5.ª columna de Próximos días en el panel; sin estado vacío.
     variant="card": tarjeta del dashboard, con invitación si aún no hay ejemplares. --}}
@props(['buddy', 'variant' => 'card', 'mood' => null, 'balls' => 0, 'date' => null, 'checkinDone' => false, 'streak' => null, 'challenge' => null, 'night' => false, 'closeHref' => null, 'closing' => null, 'checkinAvailable' => true])

@if ($variant === 'hero')
    {{-- CHG-075/081: bienvenida del Inicio. Izquierda: el compañero saluda y pide el check-in.
         Derecha: «Tu día» con la racha (CHG-076) y el reto (CHG-077) como mosaicos. --}}
    @php
        $empty = ! $buddy || $buddy['empty'];
        $hasDay = $streak || $challenge;
    @endphp
    {{-- CHG-070: de noche el escenario se oscurece; `closeHref` invita a cerrar el día desde el Inicio y `closing` es la frase del compañero en la pantalla del cierre. --}}
    <section {{ $attributes->class(['game-buddy-hero', 'game-buddy-stage', 'game-buddy-hero--night' => $night, 'game-buddy-hero--with-day' => $hasDay, 'game-stage--'.($empty ? 'cielo' : ($buddy['stage'] ?? 'cielo')), $empty ? '' : $buddy['type_class']]) }} aria-label="Bienvenida del día">
        <span class="game-buddy-stage__scene" aria-hidden="true"></span>
        <div class="game-buddy-hero__greeting">
            @if ($empty)
                <a href="{{ route('game.balls') }}" wire:navigate class="game-buddy-hero__art" aria-label="Ir a Balls"><x-game.ball class="game-buddy__ball" /></a>
            @else
                <a href="{{ route('game.collection', ['species' => $buddy['species_id']]) }}" wire:navigate class="game-buddy-hero__art game-buddy-stage__art" aria-label="Ver ficha de {{ $buddy['name'] }}">
                    @if ($buddy['image'])
                        <img src="{{ $buddy['image'] }}" width="120" height="120" loading="lazy" alt="">
                    @else
                        <span class="game-placeholder" aria-hidden="true"><i class="bi bi-stars"></i></span>
                    @endif
                    @if ($night)<span class="game-buddy-hero__zzz" aria-hidden="true">z<span>z</span><span>z</span></span>@endif
                </a>
            @endif
            <div class="game-buddy-hero__body">
                <p class="game-buddy-hero__meta md-label-medium">
                    @if ($date)<span>{{ $date }}</span>@endif
                    @unless ($empty)<span>{{ $buddy['name'] }} · Nv {{ $buddy['level'] }}</span>@endunless
                </p>
                <p class="game-buddy-hero__quote md-title-large">{{ $closing['quote'] ?? ($empty ? 'Abre tu primera ball para conocer a tu compañero.' : '“'.$buddy['quote'].'”') }}</p>
                <div class="game-buddy-hero__bubble md-body-medium">
                    @if ($closing)
                        <span><i class="bi {{ $closing['done'] ? 'bi-moon-stars-fill' : 'bi-moon-stars' }}" aria-hidden="true"></i> {{ $closing['bubble'] }}</span>
                        @unless ($closing['done'])
                            <button type="button" class="md-btn-filled game-buddy-hero__cta" wire:click="finish" wire:loading.attr="disabled"><i class="bi bi-moon-stars" aria-hidden="true"></i> Listo, terminé el día</button>
                        @endunless
                    @elseif ($closeHref)
                        <span>¿Cerramos el día?</span>
                        @if ($checkinAvailable && ! $checkinDone)
                            <button type="button" class="md-btn-text game-buddy-hero__cta" wire:click="$set('checkin', true)">Check-in</button>
                        @endif
                        <a href="{{ $closeHref }}" wire:navigate class="md-btn-filled game-buddy-hero__cta" data-testid="day-close-entry"><i class="bi bi-moon-stars" aria-hidden="true"></i> Cerrar el día</a>
                    @elseif (! $checkinAvailable)
                        <span>Un paso a tu ritmo. Consulta lo que preparaste para hoy.</span>
                    @elseif ($checkinDone)
                        <span><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Ya hiciste tu check-in de hoy.</span>
                        <button type="button" class="md-btn-text game-buddy-hero__cta" wire:click="$set('checkin', true)">Editar</button>
                    @else
                        <span>¿Cómo vas hoy?</span>
                        <button type="button" class="md-btn-filled game-buddy-hero__cta" wire:click="$set('checkin', true)"><i class="bi bi-lightning-charge" aria-hidden="true"></i> Hacer check-in</button>
                    @endif
                </div>
            </div>
        </div>
        @if ($hasDay)
            <div class="game-buddy-hero__day" role="group" aria-label="Tu día">
                @if ($streak)
                    <x-game.streak-line :streak="$streak" />
                @endif
                @if ($challenge)
                    <x-game.daily-challenge :challenge="$challenge" in-home />
                @endif
            </div>
        @endif
    </section>
@elseif ($buddy && $variant === 'wall')
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
