<x-module-shell module="game">
    <div aria-live="polite">
        @if ($error)
            <div class="md-card-filled mb-3 py-3" role="alert">{{ $error }}</div>
        @endif
    </div>

    <section class="md-card-elevated">
        <div class="d-flex justify-content-between align-items-baseline flex-wrap gap-2 mb-3">
            <h2 class="md-title-medium mb-0">{{ $pending->count() }} por abrir</h2>
            @if ($pending->isNotEmpty())
                <p class="md-body-small game-muted mb-0">Toca una ball para abrirla</p>
            @endif
        </div>

        @if ($pending->isEmpty())
            <x-ui.state variant="empty" icon="bi-circle-half" title="No tienes balls por abrir"
                        message="Llegan al completar tareas o hábitos." />
        @else
            <ul class="game-balls" aria-label="Balls por abrir">
                @foreach ($pending as $ball)
                    @php
                        $event = $ball->event;
                        $kind = match (true) {
                            $event?->kind === \App\Models\Game\GameEvent::KIND_MISSION_REWARD => 'Misión',
                            ($event?->effect['source'] ?? null) === 'habits' => 'Hábitos',
                            ($event?->effect['source'] ?? null) === 'focus' => 'Foco',
                            ($event?->effect['source'] ?? null) === 'goals' => 'Meta',
                            ($event?->effect['source'] ?? null) === 'health' => 'Salud',
                            default => 'Tarea',
                        };
                        $label = $event?->source_label ?? 'Actividad';
                        $when = $ball->granted_at->isToday() ? 'hoy '.$ball->granted_at->format('g:i a') : ($ball->granted_at->isYesterday() ? 'ayer' : $ball->granted_at->translatedFormat('j M'));
                    @endphp
                    <li wire:key="ball-{{ $ball->id }}">
                        <button type="button" class="md-btn-text game-balls__item" wire:click="open({{ $ball->id }})"
                                wire:loading.attr="disabled" wire:target="open({{ $ball->id }})"
                                aria-label="Abrir ball de {{ $kind }}: {{ $label }}, {{ $when }}">
                            <x-game.ball size="md" />
                            <span class="md-label-large">{{ $kind }}</span>
                            <span class="md-body-small">{{ \Illuminate\Support\Str::limit($label, 40) }}</span>
                            <span class="md-label-small game-muted">{{ $when }}</span>
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <div x-data="{ revealOpen: $wire.entangle('showReveal').live }">
    <x-ui.dialog standard persistent-content state="revealOpen" title="Revelación" icon="bi-stars">
        @if ($revealed)
            @php
                $revealedSpecies = \App\Models\Game\GameSpecies::find($revealed['species_id']);
                $rarity = $revealedSpecies?->rarity ?? 'common';
            @endphp
            {{-- El resultado ya está guardado; esta secuencia solo lo presenta: tiembla → destello → aparición. --}}
            <div class="game-reveal game-rarity--{{ $rarity }}"
                 wire:key="reveal-{{ $revealed['species_id'] }}-{{ $revealed['companion_xp'] }}-{{ $revealed['level_to'] ?? 0 }}"
                 x-data="{ phase: '' }"
                 x-init="
                     if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) { phase = 'is-revealed'; return; }
                     phase = 'is-shaking';
                     setTimeout(() => phase = 'is-burst', 900);
                     setTimeout(() => phase = 'is-revealed', 1200);
                 "
                 :class="phase === 'is-shaking' ? '' : phase">
                <div class="game-reveal__stage">
                    <span class="game-reveal__burst" aria-hidden="true"></span>
                    <x-game.ball size="lg" class="game-reveal__ball" x-bind:class="{ 'is-shaking': phase === 'is-shaking' }" />
                    @if ($revealed['evolved'] ?? false)
                        <div class="game-reveal__art game-reveal__art--evolution">
                            @foreach ([['evolved_from_image', 'evolved_from_name'], ['evolved_to_image', 'evolved_to_name']] as $index => [$imageKey, $nameKey])
                                @if ($index === 1)<i class="bi bi-arrow-right game-evolution__arrow" aria-hidden="true"></i>@endif
                                @if ($revealed[$imageKey])
                                    <img src="{{ $revealed[$imageKey] }}" alt="{{ $revealed[$nameKey] }}" width="96" height="96" class="{{ $index === 1 ? 'game-evolution__to' : 'game-evolution__from' }}">
                                @else
                                    <span class="game-placeholder {{ $index === 1 ? 'game-evolution__to' : 'game-evolution__from' }}" aria-hidden="true"><i class="bi bi-stars"></i></span>
                                @endif
                            @endforeach
                        </div>
                    @else
                        <div class="game-reveal__art">
                            @if ($revealed['image'])
                                <img src="{{ $revealed['image'] }}" alt="{{ $revealed['name'] }}" width="180" height="180">
                            @else
                                <span class="game-placeholder" aria-hidden="true"><i class="bi bi-stars"></i></span>
                            @endif
                        </div>
                    @endif
                </div>

                <div role="status" aria-live="assertive" x-show="phase === 'is-revealed'" x-transition.opacity>
                    @if ($revealed['evolved'] ?? false)
                        <span class="game-ribbon">¡Evolución!</span>
                        <p class="md-title-large mt-2 mb-1">¡{{ $revealed['evolved_from_name'] }} evolucionó a {{ $revealed['evolved_to_name'] }}!</p>
                    @elseif ($revealed['is_new'])
                        <span class="game-ribbon">¡Nueva!</span>
                        <p class="md-title-large mt-2 mb-1">#{{ str_pad($revealed['number'], 3, '0', STR_PAD_LEFT) }} {{ $revealed['name'] }}</p>
                        <x-game.rarity-tag :rarity="$rarity" />
                    @else
                        <span class="game-ribbon">+{{ $revealed['xp_gained'] }} xp</span>
                        <p class="md-title-large mt-2 mb-1">{{ $revealed['companion_name'] ?? $revealed['name'] }} ganó {{ $revealed['xp_gained'] }} de experiencia</p>
                    @endif
                    @if (($revealed['level_to'] ?? 0) > ($revealed['level_from'] ?? 0))
                        <p class="md-body-medium mb-1"><i class="bi bi-arrow-up-circle-fill" aria-hidden="true"></i> Subió al nivel {{ $revealed['level_to'] }}</p>
                    @endif
                    @if ($revealed['ready_to_evolve'] ?? false)
                        <p class="md-body-medium mb-1">Listo para evolucionar: elige en su ficha.</p>
                    @endif
                    @if ($revealed['source_label'])
                        <p class="md-body-medium game-muted mb-0">Llegó por: {{ $revealed['source_label'] }}</p>
                    @endif
                </div>
            </div>
            <div class="d-flex gap-2 justify-content-end mt-3">
                <a href="{{ route('game.collection', ['species' => $revealed['species_id']]) }}" class="md-btn-outlined" wire:navigate>Ver ficha</a>
                @if ($pending->isNotEmpty())
                    <button type="button" class="md-btn-filled" wire:click="openNext">Abrir otra</button>
                @endif
            </div>
        @endif
    </x-ui.dialog>
    </div>
</x-module-shell>
