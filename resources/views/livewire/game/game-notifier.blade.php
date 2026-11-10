{{-- Avisos del juego como tarjetas de logro arriba a la derecha (CHG-038; lógica de CHG-034).
     La pila es fija SIN transform en el contenedor (corrección de CHG-034) y queda bajo la
     barra superior, así ninguna barra inferior (Debug Bar, navegación móvil) la tapa. --}}
<div wire:poll.60s.visible="check" class="game-notifier" data-game-sound="{{ $sounds ? 1 : 0 }}">
    <div class="game-toasts" role="status" aria-live="polite">
        @foreach ($toasts as $toast)
            <div class="game-toast game-toast--{{ $toast['type'] }}" wire:key="game-toast-{{ $toast['id'] }}"
                 {{-- Cierre a los 5 s; pasar el mouse o enfocar lo pausa (y la barra de tiempo). --}}
                 x-data="{ left: 5000, paused: false, timer: null }"
                 x-init="timer = setInterval(() => {
                         if (paused) return;
                         left -= 100;
                         $el.style.setProperty('--game-toast-left', Math.max(0, left / 5000));
                         if (left <= 0) { clearInterval(timer); $wire.dismiss({{ $toast['id'] }}); }
                     }, 100)"
                 x-on:mouseenter="paused = true" x-on:mouseleave="paused = false"
                 x-on:focusin="paused = true" x-on:focusout="paused = false"
                 x-bind:class="{ 'is-paused': paused }">
                <span class="game-toast__badge" aria-hidden="true">
                    @if ($toast['icon'])
                        <i class="bi {{ $toast['icon'] }}"></i>
                    @else
                        <x-game.ball class="game-toast__ball" />
                    @endif
                </span>
                <div class="game-toast__text">
                    <p class="game-toast__eyebrow mb-0">{{ $toast['title'] }}</p>
                    @if ($toast['detail'])
                        <p class="md-body-medium mb-0 game-toast__detail">{{ $toast['detail'] }}</p>
                    @endif
                    @if ($toast['action'] === 'ball')
                        <a href="{{ route('game.balls') }}" wire:navigate class="md-btn-tonal game-toast__action">Abrir ball</a>
                    @endif
                </div>
                <button type="button" class="md-btn-icon game-toast__close" wire:click="dismiss({{ $toast['id'] }})" aria-label="Cerrar aviso">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
            </div>
        @endforeach
    </div>
</div>
