<x-module-shell module="game">
    <div aria-live="polite">
        @if ($error)
            <div class="md-card-filled mb-3 py-3" role="alert">{{ $error }}</div>
        @endif
    </div>

    @if ($streak)
        <x-game.streak-card :streak="$streak" class="mb-3" />
    @endif
    @if ($challenge)
        <x-game.daily-challenge :challenge="$challenge" class="md-card-filled mb-3" />
    @endif

    <section aria-label="Tus Poké Balls">
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
                            $event?->kind === \App\Models\Game\GameEvent::KIND_BONUS => 'Extra',
                            ($event?->effect['source'] ?? null) === 'habits' => 'Hábitos',
                            ($event?->effect['source'] ?? null) === 'focus' => 'Foco',
                            ($event?->effect['source'] ?? null) === 'goals' => 'Meta',
                            ($event?->effect['source'] ?? null) === 'health' => 'Salud',
                            default => 'Tarea',
                        };
                        $label = $event?->source_label ?? 'Actividad';
                        $when = $ball->granted_at->isToday() ? 'hoy '.$ball->granted_at->format('g:i a') : ($ball->granted_at->isYesterday() ? 'ayer' : $ball->granted_at->translatedFormat('j M'));
                    @endphp
                    <li class="game-ball-card" wire:key="ball-{{ $ball->id }}">
                        <div class="game-ball-card__art" aria-hidden="true"><x-game.opening-ball /><span class="game-ball-card__spark">✦</span></div>
                        <div class="game-ball-card__body">
                            <span class="game-ball-card__kind">{{ $kind }}</span>
                            <h3 class="game-ball-card__title">{{ $label }}</h3>
                            <time datetime="{{ $ball->granted_at->toIso8601String() }}" class="md-body-small game-muted">{{ $when }}</time>
                            <button type="button" class="md-btn-filled" wire:click="open({{ $ball->id }})"
                                    wire:loading.attr="disabled" wire:target="open,openNext"
                                    aria-label="Abrir Poké Ball de {{ $kind }}: {{ $label }}, {{ $when }}">
                                <span wire:loading.remove wire:target="open({{ $ball->id }})">Abrir Poké Ball <i class="bi bi-stars" aria-hidden="true"></i></span>
                                <span wire:loading wire:target="open({{ $ball->id }})" role="status">Abriendo…</span>
                            </button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <div x-data="gameReveal($wire)">
    <x-ui.dialog standard persistent-content state="revealOpen" title="Tu descubrimiento" icon="bi-stars" data-module="game">
        @if ($revealed)
            @php
                $rarity = $revealedSpecies?->rarity ?? 'common';
                $revealedCompanion = isset($revealed['companion_id']) ? \App\Models\Game\GameCompanion::find($revealed['companion_id']) : null;
                $revealedPower = $revealedCompanion ? app(\App\Services\Game\CompanionProgress::class)->power($revealedCompanion) : null;
            @endphp
            {{-- La apertura es persistente; esta secuencia solo presenta su resultado. --}}
            <div class="game-discovery game-rarity--{{ $rarity }}" :class="'game-discovery--' + phase" wire:key="discovery-{{ $revealKey }}">
                <div class="game-discovery__stage">
                    <div class="game-discovery__halo" aria-hidden="true"></div>
                    <div class="game-discovery__particles" aria-hidden="true"><span>✦</span><span>✧</span><span>✦</span><span>✧</span></div>
                    <x-game.opening-ball class="game-discovery__ball" />
                    @if ($revealed['evolved'] ?? false)
                        <div class="game-discovery__art game-reveal__art--evolution">
                            @foreach ([['evolved_from_image', 'evolved_from_name'], ['evolved_to_image', 'evolved_to_name']] as $index => [$imageKey, $nameKey])
                                @if ($index === 1)<i class="bi bi-arrow-right game-evolution__arrow" aria-hidden="true"></i>@endif
                                @if ($revealed[$imageKey])
                                    <img src="{{ $revealed[$imageKey] }}" alt="{{ $revealed[$nameKey] }}" width="96" height="96" class="{{ $index === 1 ? 'game-evolution__to' : 'game-evolution__from' }}">
                                @else
                                    <span class="game-placeholder {{ $index === 1 ? 'game-evolution__to' : 'game-evolution__from' }}" aria-hidden="true"><i class="bi bi-stars"></i></span>
                                @endif
                            @endforeach
                        </div>
                    @elseif ($revealed['is_new'] && $revealedSpecies)
                        {{-- Especie nueva: se presenta como carta coleccionable que se voltea (CHG-033). --}}
                        <div class="game-discovery__art">
                            <x-game.collectible-card class="game-discovery__card" :species="$revealedSpecies" :level="$revealed['level_to'] ?? 1"
                                                     :level-xp="($revealed['companion_xp'] ?? 0) % max(1, (int) config('game.xp_per_level'))"
                                                     :xp-per-level="max(1, (int) config('game.xp_per_level'))"
                                                     :power="$revealedPower"
                                                     :image="$revealed['image']" flip />
                        </div>
                    @else
                        <div class="game-discovery__art">
                            @if ($revealed['image'])
                                <img src="{{ $revealed['image'] }}" alt="{{ $revealed['name'] }}" width="220" height="220">
                            @else
                                <span class="game-placeholder" aria-hidden="true"><i class="bi bi-stars"></i></span>
                            @endif
                        </div>
                    @endif
                </div>

                <button type="button" class="md-btn-text" x-show="!ready" @click="finish()">Mostrar ahora</button>
                <div role="status" aria-live="polite" x-show="ready" x-cloak class="game-discovery__details">
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
                    @unless ($revealed['is_new'] && !($revealed['evolved'] ?? false))<p class="md-label-large game-muted mb-1">#{{ str_pad($revealed['number'], 3, '0', STR_PAD_LEFT) }} · {{ $revealed['name'] }}</p>@endunless
                    <div class="game-discovery__types">
                        @foreach ($revealedSpecies?->types ?? [] as $type)
                            <span class="game-type-chip {{ \App\Services\Game\CompanionProgress::typeClass($type) }}">{{ $type }}</span>
                        @endforeach
                        @unless ($revealed['is_new'] && !($revealed['evolved'] ?? false))<x-game.rarity-tag :rarity="$rarity" />@endunless
                    </div>
                    <div class="game-discovery__bio" tabindex="0" role="region" aria-label="Descripción del Pokémon">
                        @if ($revealed['evolved'] ?? false)<h3 class="md-title-small">Sobre {{ $revealed['name'] }}</h3>@endif
                        <p class="md-body-medium mb-0">{{ $revealedSpecies?->description ?: 'Aún no tenemos una descripción de este Pokémon' }}</p>
                    </div>
                    @if ($revealed['source_label'])
                        <p class="md-body-medium game-muted mb-0">Llegó por: {{ $revealed['source_label'] }}</p>
                    @endif
                </div>
            </div>
        @endif
        <x-slot:actions>
            @if ($revealed)
                <div x-show="ready" x-cloak class="game-discovery__actions">
                    <a href="{{ route('game.collection', ['species' => $revealed['species_id']]) }}" class="md-btn-outlined" wire:navigate>Ver ficha</a>
                    @if ($pending->isNotEmpty())
                        <button type="button" class="md-btn-filled" wire:click="openNext" wire:loading.attr="disabled" wire:target="open,openNext">Abrir otra</button>
                    @endif
                </div>
            @endif
        </x-slot:actions>
    </x-ui.dialog>
    </div>
</x-module-shell>
