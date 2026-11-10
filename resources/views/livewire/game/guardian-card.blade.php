{{-- Guardián de módulo (CHG-055/057): franja con el escenario de su tipo y un consejo según tus datos. --}}
<div>
    @if ($guardian)
        <div @class(['game-guardian', 'game-guardian--'.$variant, 'game-stage--'.($guardian['stage'] ?? 'cielo'), $guardian['type_class']])
             wire:poll.{{ $refresh }}s.visible="refreshAdvice">
            <a href="{{ route('game.collection', ['species' => $guardian['species_id']]) }}" wire:navigate
               class="game-guardian__art" aria-label="Ver ficha de {{ $guardian['name'] }}">
                @if ($guardian['image'])
                    <img src="{{ $guardian['image'] }}" width="96" height="96" loading="lazy" alt="">
                @else
                    <span class="game-placeholder" aria-hidden="true"><i class="bi bi-stars"></i></span>
                @endif
            </a>
            <div class="game-guardian__talk">
                <span class="game-guardian__name">{{ $guardian['name'] }} · Nv {{ $guardian['level'] }}</span>
                {{-- El consejo ya viene escapado: solo los nombres van envueltos en <strong>. --}}
                <p class="game-guardian__bubble" aria-live="polite" wire:key="advice-{{ $adviceKey }}-{{ md5($advice) }}">“{!! $advice !!}”</p>
                <div class="game-guardian__actions">
                    @if ($action)
                        <button type="button" class="game-guardian__cue" x-data
                                x-on:click="document.getElementById(@js($action['target']))?.focus()">
                            {{ $action['label'] }} <i class="bi bi-arrow-down" aria-hidden="true"></i>
                        </button>
                    @endif
                    <x-ui.icon-action icon="bi-arrow-repeat" label="Otro consejo" size="sm"
                                      class="game-guardian__more" wire:click="another"
                                      wire:loading.attr="disabled" wire:target="another" />
                </div>
            </div>
        </div>
    @endif
</div>
