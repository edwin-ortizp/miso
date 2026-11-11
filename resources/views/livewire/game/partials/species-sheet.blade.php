@php
    $species = $sheet['species'];
    $number = '#'.str_pad($species->number, 3, '0', STR_PAD_LEFT);
    $companion = $sheet['companion'];
    $discovered = $sheet['discovered'];
    $typeClass = \App\Services\Game\CompanionProgress::typeClass($species->types[0] ?? null);
    $statLabels = ['hp' => 'PS', 'attack' => 'Ataque', 'defense' => 'Defensa', 'special_attack' => 'At. especial', 'special_defense' => 'Def. especial', 'speed' => 'Velocidad'];
    $timelineIcons = ['discovered' => 'bi-stars', 'xp_gained' => 'bi-plus-lg', 'level_up' => 'bi-arrow-up', 'evolved' => 'bi-lightning-charge-fill'];
@endphp

<div class="game-sheet {{ $discovered ? $typeClass : '' }} game-rarity--{{ $species->rarity }}" wire:key="sheet-{{ $species->id }}">
    @if ($sheetMessage ?? '')
        <div class="md-card-filled mb-3 py-2" role="status" aria-live="polite">{{ $sheetMessage }}</div>
    @endif

    <div class="game-sheet__layout {{ $discovered ? '' : 'game-sheet__layout--hidden' }}">
        <div class="game-sheet__content">
            <section x-show="section === 'profile'" aria-label="Ficha del Pokémon" tabindex="0">
                <header class="game-sheet__hero {{ $discovered ? '' : 'is-hidden' }}">
                    <div>
                        <p class="md-label-large mb-0">{{ $number }}</p>
                        <h3 class="md-headline-medium mb-2">{{ $discovered ? $species->name : '?' }}</h3>
                        @if ($discovered)
                            <div class="d-flex flex-wrap align-items-center gap-2" aria-label="Rareza y tipos">
                                <x-game.rarity-tag :rarity="$species->rarity" />
                                @foreach ($species->types ?? [] as $type)
                                    <span class="game-type-chip">{{ $type }}</span>
                                @endforeach
                            </div>
                        @else
                            <p class="md-body-medium mb-0">Aún no la descubres</p>
                        @endif
                    </div>
                    <div class="game-sheet__art">
                        @if ($sheet['image'])
                            <img src="{{ $sheet['image'] }}" width="180" height="180" alt="{{ $discovered ? $species->name : 'Especie sin descubrir' }}">
                        @else
                            <span class="game-placeholder" aria-hidden="true"><i class="bi {{ $discovered ? 'bi-stars' : 'bi-question-lg' }}"></i></span>
                        @endif
                    </div>
                </header>

                @if ($discovered && $companion && $sheet['isCurrent'])
                    @php $atMaxLevel = $companion->level >= (int) config('game.max_level'); @endphp
                    <section class="game-experience" aria-label="Experiencia de tu compañero">
                        <div class="game-experience__heading">
                            <span class="game-experience__emblem" aria-hidden="true"><i class="bi bi-stars"></i></span>
                            <div>
                                <h4>Tu experiencia ganada</h4>
                                <p>Cada pequeño paso lo ha hecho crecer.</p>
                            </div>
                            <span class="game-experience__level">Nivel {{ $companion->level }}</span>
                        </div>
                        <div class="game-experience__total"><strong>{{ number_format($companion->xp, 0, ',', '.') }}</strong><span>XP acumulada</span></div>
                        <x-ui.progress :value="$atMaxLevel ? $sheet['xpPerLevel'] : $sheet['levelXp']" :max="$sheet['xpPerLevel']"
                                       :label="$atMaxLevel ? 'Nivel máximo alcanzado' : 'Experiencia del nivel'"
                                       :value-text="$atMaxLevel ? '¡Lo lograste!' : $sheet['levelXp'].' / '.$sheet['xpPerLevel'].' xp'" />
                        <div class="game-experience__next">
                            <i class="bi {{ $atMaxLevel ? 'bi-trophy-fill' : 'bi-arrow-up-circle-fill' }}" aria-hidden="true"></i>
                            @if ($atMaxLevel)
                                <span>Alcanzó el nivel máximo. La experiencia que siga ganando se conserva.</span>
                            @else
                                <span><strong>{{ $sheet['xpPerLevel'] - $sheet['levelXp'] }} XP</strong> para llegar al <strong>nivel {{ $companion->level + 1 }}</strong></span>
                            @endif
                        </div>
                    </section>
                @endif

                @if ($discovered)
                    @if ($species->description)
                        <p class="md-body-large mt-3">{{ $species->description }}</p>
                    @endif

                @endif
                <section class="game-sheet__section">
                    <h4 class="md-title-medium mb-2">Evoluciones</h4>
                    <ol class="game-chain">
                        @foreach ($sheet['chain'] as $index => $link)
                            @if ($index > 0)<li class="game-chain__arrow" aria-hidden="true"><i class="bi bi-arrow-right"></i></li>@endif
                            <li class="game-chain__item {{ $link['discovered'] ? 'is-discovered' : '' }} {{ $link['species']->id === $species->id ? 'is-current' : '' }}">
                                @if ($link['image'])
                                    <img src="{{ $link['image'] }}" width="56" height="56" loading="lazy" alt="">
                                @else
                                    <span aria-hidden="true">{{ $link['discovered'] ? '●' : '○' }}</span>
                                @endif
                                <span>#{{ str_pad($link['species']->number, 3, '0', STR_PAD_LEFT) }}</span>
                                <span>{{ $link['discovered'] ? $link['species']->name : '?' }}</span>
                            </li>
                        @endforeach
                    </ol>
                </section>

            </section>
            <div class="game-sheet__history" x-show="section === 'history'" x-cloak tabindex="0" aria-label="Historial del Pokémon">
                @if ($discovered && $companion)
                    <section class="game-sheet__section">
                        <h4 class="md-title-medium mb-2">Línea de tiempo</h4>
                        <p class="md-body-small game-muted">Creció gracias a:</p>
                        @if ($sheet['history']->isEmpty())
                            <p class="md-body-medium game-muted">Aún no ha crecido. Cada ball de su línea suma.</p>
                        @else
                            <ol class="game-timeline">
                                @foreach ($sheet['history'] as $event)
                                    @php
                                        $effect = $event->effect ?? [];
                                        $what = match ($event->kind) {
                                            'discovered' => 'Lo conociste'.(($effect['xp'] ?? 0) ? ' · +'.$effect['xp'].' xp' : ''),
                                            'xp_gained' => 'Ganó '.($effect['xp'] ?? 0).' xp',
                                            'level_up' => 'Subió al nivel '.($effect['to'] ?? ''),
                                            'evolved' => 'Evolucionó a '.(\App\Models\Game\GameSpecies::find($effect['to_species_id'] ?? 0)?->name ?? ''),
                                            default => '',
                                        };
                                    @endphp
                                    <li class="game-timeline__item game-timeline__item--{{ $event->kind }}" wire:key="growth-{{ $event->id }}">
                                        <span class="game-timeline__icon" aria-hidden="true"><i class="bi {{ $timelineIcons[$event->kind] ?? 'bi-dot' }}"></i></span>
                                        <p class="md-body-medium mb-0"><strong>{{ $what }}</strong></p>
                                        <p class="md-body-small game-muted mb-0">{{ $event->occurred_at->translatedFormat('j M Y') }} · {{ $event->source_label ?: 'Actividad' }}</p>
                                    </li>
                                @endforeach
                            </ol>
                            @if (! ($readonly ?? false) && $sheet['historyTotal'] > $sheet['history']->count())
                                <button type="button" class="md-btn-text mt-2" wire:click="moreHistory">Ver más</button>
                            @endif
                        @endif
                    </section>
                @endif
            </div>
        </div>
        @if ($discovered)
            <aside class="game-sheet__properties" aria-label="Características del Pokémon" tabindex="0">
                <h3 class="md-title-medium mb-0">Características</h3>
                <p class="md-label-large game-muted mb-0">{{ $number }} · {{ $species->name }}</p>
                <div class="game-sheet__headline">
                    @if ($companion && $sheet['isCurrent'])
                        <span class="game-power md-title-large"><i class="bi bi-lightning-charge-fill" aria-hidden="true"></i>Poder {{ $sheet['power'] }}</span>
                    @endif
                </div>

                @if ($species->stats)
                    <dl class="game-stats" aria-label="Estadísticas">
                        @foreach ($statLabels as $key => $label)
                            @isset($species->stats[$key])
                                @php $value = (int) $species->stats[$key]; @endphp
                                <div class="game-stats__row">
                                    <dt class="md-label-large">{{ $label }}</dt>
                                    <span class="game-stats__bar" aria-hidden="true"><span style="--md-progress-value: {{ min(100, round($value / 255 * 100)) }}%"></span></span>
                                    <dd class="md-title-small">{{ $value }}</dd>
                                </div>
                            @endisset
                        @endforeach
                    </dl>
                @endif

                @if ($companion)
                    <section class="game-sheet__section">
                        @if ($sheet['isCurrent'])
                            <h4 class="md-title-medium mb-2">Tu ejemplar</h4>
                            @if ($sheet['inTeam'] ?? false)
                                <p class="md-body-medium mb-2"><i class="bi bi-people-fill" aria-hidden="true"></i> En tu equipo esta semana · <a href="{{ route('game.week') }}" wire:navigate>Ver semana</a></p>
                            @endif
                            <p class="md-body-medium mt-2 mb-1">Lo conociste el {{ $companion->first_met_at->translatedFormat('j M Y') }}</p>
                            @if ($sheet['nextLevel'])
                                <p class="md-body-medium mb-1">Evoluciona en el nivel {{ $sheet['nextLevel'] }}</p>
                            @elseif ($sheet['branchOptions']->isNotEmpty())
                                <p class="md-body-medium mb-2"><strong>Listo para evolucionar</strong></p>
                                @if ($readonly ?? false)
                                    <p class="md-body-medium mb-0">Elige su evolución desde la Colección.</p>
                                @else
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach ($sheet['branchOptions'] as $option)
                                        <button type="button" class="md-btn-tonal" wire:click="evolve({{ $companion->id }}, {{ $option->id }})">Evolucionar a {{ $option->name }}</button>
                                    @endforeach
                                </div>
                                @endif
                            @elseif ($sheet['isFinal'])
                                <p class="md-body-medium mb-1">Ya está en su última etapa</p>
                            @endif
                        @else
                            <p class="md-body-medium mb-0">Tu ejemplar de esta línea es {{ $companion->species->name }}</p>
                        @endif
                    </section>
                @endif
            </aside>
        @endif
    </div>
</div>
