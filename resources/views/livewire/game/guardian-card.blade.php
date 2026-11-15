{{-- Guardián de módulo (CHG-055/057): franja con el escenario de su tipo y un consejo según tus datos. --}}
<div>
    @if ($guardian)
        <div @class(['game-guardian', 'game-guardian--habits' => $module === 'habits', 'game-guardian--'.$variant, 'game-stage--'.($guardian['stage'] ?? 'cielo'), $guardian['type_class']])
             wire:poll.{{ $refresh }}s.visible="refreshAdvice">
            <div class="game-guardian__talk">
                <div class="game-guardian__heading">
                    <div class="game-guardian__identity">
                        <span class="game-guardian__name">{{ $guardian['name'] }} · Nv {{ $guardian['level'] }}</span>
                        @if (! empty($guardian['team_ability']))
                            <span class="game-guardian__team md-label-small">Equipo de la semana · {{ $guardian['team_ability'] }}</span>
                        @endif
                    </div>
                    @if ($module === 'habits' && ! $isOtherDate)
                        <x-ui.icon-action icon="bi-arrow-repeat" label="Otro consejo" size="sm"
                                          class="game-guardian__more" wire:click="another"
                                          wire:loading.attr="disabled" wire:target="another" />
                    @endif
                </div>
                {{-- El consejo ya viene escapado: solo los nombres van envueltos en <strong>. --}}
                <p class="game-guardian__bubble" aria-live="polite" wire:key="advice-{{ $adviceKey }}-{{ md5($advice) }}">@if ($isOtherDate){{ $dateSummary }}@else“{!! $advice !!}”@endif</p>
                @if (! $isOtherDate && ($action || $module !== 'habits'))
                <div class="game-guardian__actions">
                    @if ($action)
                        <x-ui.action variant="text" class="game-guardian__cue" x-data
                                x-on:click="document.getElementById({{ \Illuminate\Support\Js::from($action['target']) }})?.focus()">
                            {{ $action['label'] }} <i class="bi bi-arrow-down" aria-hidden="true"></i>
                        </x-ui.action>
                    @endif
                    @if ($module !== 'habits')
                    <x-ui.icon-action icon="bi-arrow-repeat" label="Otro consejo" size="sm"
                                      class="game-guardian__more" wire:click="another"
                                      wire:loading.attr="disabled" wire:target="another" />
                    @endif
                </div>
                @endif
            </div>
            {{-- CHG-062: la ficha se abre aquí mismo; ir a la Colección es opcional. --}}
            <x-ui.action variant="text" wire:click="openSheet" wire:loading.attr="disabled" wire:target="openSheet"
                    class="game-guardian__art" aria-label="Ver ficha de {{ $guardian['name'] }}">
                @if ($guardian['image'])
                    <img src="{{ $guardian['image'] }}" width="96" height="96" loading="lazy" alt="">
                @else
                    <span class="game-placeholder" aria-hidden="true"><i class="bi bi-stars"></i></span>
                @endif
            </x-ui.action>
            @if ($displayProgress !== null)
                <div class="game-guardian__progress">
                    <x-ui.progress :value="$displayProgress['done']" :max="max(1, $displayProgress['total'])"
                                   tone="success" label="Progreso del día"
                                   :value-text="$displayProgress['done'].' / '.$displayProgress['total']" />
                </div>
            @endif
        </div>

        <div x-data="{ sheetOpen: $wire.entangle('showSheet').live }">
            <x-ui.dialog standard persistent-content state="sheetOpen" title="Ficha" size="lg" icon="bi-journal-richtext" data-module="game"
                         :sections="[
                            'profile' => ['label' => 'Ficha', 'icon' => 'bi-person-badge'],
                            ...($sheet && $sheet['discovered'] && $sheet['companion'] ? ['history' => ['label' => 'Historial', 'icon' => 'bi-clock-history']] : []),
                         ]">
                @if ($sheet)
                    @include('livewire.game.partials.species-sheet', ['sheet' => $sheet, 'readonly' => true])
                @endif
                <x-slot:actions>
                    <a href="{{ route('game.collection', ['species' => $guardian['species_id']]) }}" wire:navigate class="md-btn-tonal">Ver colección</a>
                    <button type="button" class="md-btn-text" @click="sheetOpen = false">Cerrar</button>
                </x-slot:actions>
            </x-ui.dialog>
        </div>
    @endif
</div>
