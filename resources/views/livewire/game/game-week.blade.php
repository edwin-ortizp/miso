<x-module-shell module="game">
    <section aria-labelledby="game-team-title">
        <p class="md-label-large game-muted mb-1">Semana del {{ $week->week_start->translatedFormat('j') }} al {{ $weekEnd->translatedFormat('j M') }}</p>
        <h2 class="md-title-large mb-3" id="game-team-title">Tu equipo</h2>

        @if ($members->isEmpty())
            <div class="md-card-elevated">
                <x-ui.state variant="empty" icon="bi-people" title="Tu equipo se arma cuando descubras tu primer Pokémon." />
            </div>
        @else
            {{-- Cada integrante es una carta independiente (CHG-033). --}}
            <ul class="game-collection-cards" aria-label="Tu equipo">
                @foreach ($members as $row)
                    <li wire:key="member-{{ $row['member']->id }}">
                        <x-game.collectible-card :species="$row['species']" :level="$row['member']->companion->level"
                                                 :power="$row['power']" :level-xp="$row['levelXp']" :xp-per-level="$xpPerLevel"
                                                 :image="$row['image']" :ability="$row['member']->ability_label"
                                                 :href="route('game.collection', ['species' => $row['species']->id])" />
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- CHG-037: misiones como tarjetas de aventura independientes (mismo componente que Temporada). --}}
    <section class="mt-4" aria-labelledby="game-missions-title">
        <h2 class="md-title-large mb-3" id="game-missions-title">Misiones de la semana</h2>

        @if ($missions->isEmpty())
            <div class="md-card-elevated">
                <x-ui.state variant="empty" icon="bi-flag" title="Esta semana no hay misiones"
                            message="Aparecen cuando tienes categorías de tareas o hábitos." />
            </div>
        @else
            <x-game.missions :missions="$missions" :done-tasks="$doneTasks" :existing-tasks="$existingTasks" adventure />
        @endif
    </section>
</x-module-shell>
