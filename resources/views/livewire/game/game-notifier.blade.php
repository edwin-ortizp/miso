{{-- Avisos del juego como tarjetas de logro arriba a la derecha (CHG-038; lógica de CHG-034).
     La pila es fija SIN transform en el contenedor (corrección de CHG-034) y queda bajo la
     barra superior, así ninguna barra inferior (Debug Bar, navegación móvil) la tapa. --}}
<div wire:poll.60s.visible="check" class="game-notifier" data-game-sound="{{ $sounds ? 1 : 0 }}">
    <div class="game-toasts" role="status" aria-live="polite">
        @foreach ($toasts as $toast)
            <div class="game-toast game-toast--{{ $toast['type'] }}" wire:key="game-toast-{{ $toast['id'] }}"
                 {{-- Cierre a los 5 s con una animación CSS de la barra (CHG-053): fluida y sin
                      estilos inline que el morph de Livewire borre. Mouse o foco la pausan. --}}
                 x-data="{ paused: false }"
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
                    @if (! empty($toast['members']))
                        {{-- La foto de cada Pokémon que ganó la experiencia (CHG-050). --}}
                        <ul class="game-toast__members">
                            @foreach (array_slice($toast['members'], 0, 3) as $member)
                                <li class="game-toast__member">
                                    <span class="game-toast__avatar" aria-hidden="true">
                                        @if ($member['image'])
                                            <img src="{{ $member['image'] }}" alt="" width="28" height="28" loading="lazy">
                                        @else
                                            {{ mb_substr($member['name'], 0, 1) }}
                                        @endif
                                    </span>
                                    <span class="game-toast__member-text">
                                        {{ $member['name'] }} +{{ $member['xp'] }}@if ($member['affinity'])<span class="game-toast__affinity">afinidad</span>@endif
                                    </span>
                                </li>
                            @endforeach
                            @if (count($toast['members']) > 3)
                                <li class="game-toast__member game-toast__member--more">y {{ count($toast['members']) - 3 }} más</li>
                            @endif
                        </ul>
                        @if ($toast['note'] ?? null)
                            <p class="game-toast__note mb-0">{{ $toast['note'] }}</p>
                        @endif
                    @elseif ($toast['detail'])
                        <p class="md-body-medium mb-0 game-toast__detail">{{ $toast['detail'] }}</p>
                    @endif
                    @if ($toast['action'] === 'ball')
                        <a href="{{ route('game.balls') }}" wire:navigate class="md-btn-tonal game-toast__action">Abrir ball</a>
                    @endif
                </div>
                <button type="button" class="md-btn-icon game-toast__close" wire:click="dismiss({{ $toast['id'] }})" aria-label="Cerrar aviso">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
                <span class="game-toast__timer" aria-hidden="true"
                      x-on:animationend.self="$wire.dismiss({{ $toast['id'] }})"></span>
            </div>
        @endforeach
    </div>
</div>
