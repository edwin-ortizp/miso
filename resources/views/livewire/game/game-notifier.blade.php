{{-- Avisos del juego (CHG-034). La pila es fija y centrada SIN transform en el
     contenedor: así cada aviso se posiciona bien (el defecto de CHG-023 venía de
     un snackbar fijo dentro de un ancestro con transform). --}}
<div wire:poll.60s.visible="check" class="game-notifier">
    <div class="game-toasts" role="status" aria-live="polite">
        @foreach ($toasts as $toast)
            <div class="game-toast game-toast--{{ $toast['type'] }}" wire:key="game-toast-{{ $toast['id'] }}"
                 x-data x-init="setTimeout(() => $wire.dismiss({{ $toast['id'] }}), 5000)">
                @if ($toast['icon'])
                    <i class="bi {{ $toast['icon'] }} game-toast__icon" aria-hidden="true"></i>
                @else
                    <x-game.ball class="game-toast__ball" />
                @endif
                <div class="game-toast__text">
                    <p class="md-body-medium mb-0"><strong>{{ $toast['title'] }}</strong></p>
                    @if ($toast['detail'])
                        <p class="md-body-small mb-0 game-toast__detail">{{ $toast['detail'] }}</p>
                    @endif
                </div>
                @if ($toast['action'] === 'ball')
                    <a href="{{ route('game.balls') }}" wire:navigate class="md-btn-text game-toast__action">Abrir</a>
                @endif
                <button type="button" class="md-btn-icon game-toast__close" wire:click="dismiss({{ $toast['id'] }})" aria-label="Cerrar aviso">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
            </div>
        @endforeach
    </div>
</div>
