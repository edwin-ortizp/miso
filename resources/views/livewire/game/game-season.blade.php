<x-module-shell module="game">
    @if ($message)
        <div class="md-card-filled mb-3 py-3" role="status" aria-live="polite">{{ $message }}</div>
    @endif

    @php
        $themeKey = $active?->config['theme'] ?? null;
        $theme = $themeKey ? (config('game.season_themes')[$themeKey] ?? null) : null;
    @endphp
    <div @class(['game-adventure', 'game-season--theme-'.$themeKey => $theme])>
        <section class="game-adventure__cover" aria-label="Tu temporada">
            <div class="game-adventure__intro">
                <span class="game-adventure__eyebrow">
                    @if ($theme)
                        <i class="bi {{ $theme['icon'] }}" aria-hidden="true"></i> Temporada · {{ $theme['label'] }}
                    @else
                        <i class="bi bi-compass" aria-hidden="true"></i> Tu aventura de colección
                    @endif
                </span>
                <h2>{{ $active?->name ?? 'Cada pequeño paso abre un camino' }}</h2>
                <p>{{ $active?->narrative ?? 'Completa actividades, gana balls y descubre compañeros a tu ritmo.' }}</p>
                @if ($active && ! empty($active->config['featured_types']))
                    <div class="game-season__featured-types">
                        <span class="md-label-large">Tipos destacados</span>
                        @foreach ($active->config['featured_types'] as $type)
                            <span class="game-type-chip {{ \App\Services\Game\CompanionProgress::typeClass($type) }}">{{ \Illuminate\Support\Str::ucfirst($type) }}</span>
                        @endforeach
                    </div>
                @endif
                @if ($active)
                    <p class="game-adventure__dates">{{ $active->starts_at->translatedFormat('j M') }} – {{ $active->ends_at->translatedFormat('j M Y') }} · {{ $daysLeft }} {{ $daysLeft === 1 ? 'día restante' : 'días restantes' }}</p>
                @else
                    <p>Sin temporada activa · Puedes seguir jugando con las reglas base.</p>
                @endif
                <a href="#season-missions" class="md-btn-filled">Ver misiones de esta semana <i class="bi bi-arrow-down" aria-hidden="true"></i></a>
            </div>
            <div class="game-adventure__landscape">
                <img class="game-adventure__scenery" src="{{ asset('images/game/season-landscape.svg') }}" alt="" width="720" height="400">
                <div class="game-adventure__companions">
                    @forelse ($companions as $companion)
                        <figure>
                            @if ($companion['image'])
                                <img src="{{ $companion['image'] }}" alt="" width="112" height="112">
                            @else
                                <i class="bi bi-stars" aria-hidden="true"></i>
                            @endif
                            <figcaption>{{ $companion['name'] }}</figcaption>
                        </figure>
                    @empty
                        <p class="game-adventure__welcome">Tu primer compañero te espera dentro de una ball.</p>
                    @endforelse
                </div>
            </div>
        </section>

        @if ($active)
            <section class="game-adventure__journey" aria-label="Recorrido de temporada">
                <div class="d-flex justify-content-between gap-2 flex-wrap">
                    <h3 class="md-title-medium">Tu recorrido</h3>
                    <span class="md-body-small game-muted">El tiempo avanza; tus logros se quedan.</span>
                </div>
                <ol class="game-adventure__stages">
                    @foreach ($stages as $stage)
                        <li class="{{ $stage['current'] ? 'is-current' : ($stage['past'] ? 'is-past' : '') }}" @if ($stage['current']) aria-current="step" @endif>
                            <span class="game-adventure__milestone" aria-hidden="true"><i class="bi {{ $stage['current'] ? 'bi-geo-alt-fill' : ($stage['past'] ? 'bi-flag-fill' : 'bi-signpost-split') }}"></i></span>
                            <strong>Semana {{ $stage['number'] }}</strong>
                            <span>{{ $stage['start']->translatedFormat('j M') }}</span>
                            <span class="md-label-small">{{ $stage['current'] ? 'Estás aquí' : ($stage['past'] ? 'Recorrida' : 'Por venir') }}</span>
                        </li>
                    @endforeach
                </ol>
                <p class="md-body-small game-muted mb-0">Este mapa muestra las fechas, no tareas pendientes. Cada semana tiene sus propias misiones.</p>
            </section>
        @endif

        <section id="season-missions" class="game-adventure__missions" aria-labelledby="season-missions-title">
            <div class="game-adventure__section-heading">
                <div>
                    <span class="md-label-large game-muted">Tu próxima aventura</span>
                    <h2 id="season-missions-title" class="md-title-large">Misiones de esta semana</h2>
                </div>
                @if ($missions->isNotEmpty())
                    <x-ui.badge>{{ $missions->whereNotNull('full_rewarded_at')->count() }}/{{ $missions->count() }} completadas</x-ui.badge>
                @endif
            </div>
            @if ($missions->isEmpty())
                <x-ui.state variant="empty" icon="bi-compass" title="Tu semana está por comenzar" message="Consulta Semana para preparar tu equipo y conocer tus misiones." />
                <a href="{{ route('game.week') }}" wire:navigate class="md-btn-filled">Ir a Semana <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
            @else
                @if ($missions->every(fn ($mission) => $mission->full_rewarded_at !== null))
                    <p role="status">¡Todas las misiones completadas! Tus recompensas ya son tuyas. Puedes seguir explorando a tu ritmo.</p>
                @endif
                <x-game.missions :missions="$missions" :done-tasks="$doneTasks" :existing-tasks="$existingTasks" adventure />
            @endif
        </section>

        <div class="game-adventure__extras">
            <x-ui.card as="section" variant="outlined">
                <h3 class="md-title-medium"><i class="bi bi-lightning-charge" aria-hidden="true"></i> Un impulso extra</h3>
                @forelse ($bonuses as $bonus)
                    <div class="game-adventure__bonus">
                        <strong>+{{ $bonus['percent'] }} % de experiencia</strong>
                        <p class="md-body-medium mb-1">Para tu equipo al completar actividades de {{ $bonus['label'] }}.</p>
                        <a href="{{ $bonus['href'] }}" wire:navigate class="md-btn-text">Explorar {{ $bonus['label'] }} <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                    </div>
                @empty
                    <p class="md-body-medium">Cada actividad elegible suma a tu colección, con las reglas habituales.</p>
                @endforelse
                <p class="md-body-small game-muted mb-0">Son oportunidades opcionales. Ninguna ausencia resta lo ganado.</p>
            </x-ui.card>
            <x-ui.card as="section" variant="outlined">
                <h3 class="md-title-medium">Así crece tu colección</h3>
                <ol class="game-adventure__how">
                    <li><i class="bi bi-check2-circle" aria-hidden="true"></i> Completa actividades y avanza en tus misiones.</li>
                    <li><x-game.ball size="sm" /> Gana balls con las reglas y límites de tu juego.</li>
                    <li><i class="bi bi-stars" aria-hidden="true"></i> Ábrelas para descubrir compañeros o darles experiencia.</li>
                </ol>
                <a href="{{ route('game.balls') }}" wire:navigate class="md-btn-filled">Ver balls · {{ $pendingBalls }} por abrir <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
            </x-ui.card>
        </div>
    </div>

    <details class="game-adventure__details mt-3">
        <summary>Detalles e historial de temporadas</summary>
    <section class="md-card-elevated">
        @if ($active)
            <p class="md-label-large game-muted mb-1">{{ $active->starts_at->translatedFormat('j M') }} – {{ $active->ends_at->translatedFormat('j M Y') }}</p>
            <h3 class="md-title-medium mb-2">{{ $active->name }}</h3>
            <p class="md-body-large">{{ $active->narrative }}</p>
            @if ($activeRules)
                <ul class="game-season__rules">
                    @foreach ($activeRules as $rule)
                        <li class="md-body-medium">{{ $rule }}</li>
                    @endforeach
                </ul>
            @endif
            <div class="d-flex justify-content-end">
                <button type="button" class="md-btn-outlined" wire:click="askRevert({{ $active->id }})">Revertir temporada</button>
            </div>
        @else
            <x-ui.state variant="empty" icon="bi-calendar-event" title="Aún no hay temporada. El juego usa sus reglas base." />
        @endif

        @if ($scheduled)
            <p class="md-body-medium mt-3 mb-0">
                Siguiente: <strong>{{ $scheduled->name }}</strong> desde el {{ $scheduled->starts_at->translatedFormat('j M') }}.
                <button type="button" class="md-btn-text" wire:click="askRevert({{ $scheduled->id }})">Cancelar</button>
            </p>
        @endif
    </section>

    <p class="md-body-small game-muted mt-2 mb-0">El director y las temporadas se activan o pausan en <a href="{{ route('game.settings') }}" wire:navigate>Ajustes</a>.</p>

    <section class="md-card-elevated mt-3">
        <h2 class="md-title-medium mb-2">Historial</h2>
        @if ($history->isEmpty())
            <p class="md-body-medium game-muted mb-0">Todavía no hay temporadas.</p>
        @else
            <x-ui.list label="Historial de temporadas">
                @foreach ($history as $season)
                    @php
                        $dates = $season->starts_at ? $season->starts_at->translatedFormat('j M').' – '.$season->ends_at?->translatedFormat('j M Y') : null;
                        $supporting = collect([$season->displayStatus(), $dates, $season->errors ? implode(' ', $season->errors) : null])->filter()->implode(' · ');
                    @endphp
                    <x-ui.list-item :headline="$season->name ?: '(sin nombre)'" :supporting="$supporting" wire:key="season-{{ $season->id }}">
                        <x-slot:trailing>
                            <x-ui.badge>{{ $season->displayStatus() }}</x-ui.badge>
                        </x-slot:trailing>
                    </x-ui.list-item>
                @endforeach
            </x-ui.list>
        @endif
    </section>

    </details>

    <div x-data="{ revertOpen: $wire.entangle('confirmRevert').live }">
        <x-ui.dialog standard persistent-content state="revertOpen" title="Revertir temporada" icon="bi-arrow-counterclockwise">
            <p class="md-body-large">¿Revertir esta temporada? Sus reglas dejan de aplicarse desde ahora. No pierdes nada de lo que ganaste.</p>
            <div class="d-flex gap-2 justify-content-end mt-3">
                <button type="button" class="md-btn-text" x-on:click="revertOpen = false">Cancelar</button>
                <button type="button" class="md-btn-filled" wire:click="revert">Revertir</button>
            </div>
        </x-ui.dialog>
    </div>
</x-module-shell>
