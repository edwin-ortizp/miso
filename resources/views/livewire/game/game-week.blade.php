<x-module-shell module="game">
    <section class="md-card-elevated">
        <p class="md-label-large game-muted mb-1">Semana del {{ $week->week_start->translatedFormat('j') }} al {{ $weekEnd->translatedFormat('j M') }}</p>
        <h2 class="md-title-medium mb-3">Tu equipo</h2>

        @if ($members->isEmpty())
            <x-ui.state variant="empty" icon="bi-people" title="Tu equipo se arma cuando descubras tu primer Pokémon." />
        @else
            <ul class="game-team" aria-label="Tu equipo">
                @foreach ($members as $row)
                    <li wire:key="member-{{ $row['member']->id }}">
                        <x-game.companion-card :species="$row['species']" :companion="$row['member']->companion"
                                               :ability="$row['member']->ability_label" :power="$row['power']"
                                               :level-xp="$row['levelXp']" :xp-per-level="$xpPerLevel" :image="$row['image']"
                                               :href="route('game.collection', ['species' => $row['species']->id])" />
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="md-card-elevated mt-3">
        <h2 class="md-title-medium mb-3">Misiones de la semana</h2>

        @if ($missions->isEmpty())
            <x-ui.state variant="empty" icon="bi-flag" title="Esta semana no hay misiones"
                        message="Aparecen cuando tienes categorías de tareas o hábitos." />
        @else
            <x-ui.list label="Misiones de la semana">
                @foreach ($missions as $mission)
                    @php
                        $status = $mission->full_rewarded_at ? '¡Misión cumplida!' : ($mission->half_rewarded_at ? 'Ball recibida' : null);
                    @endphp
                    <li class="game-mission" wire:key="mission-{{ $mission->id }}">
                        <div class="d-flex justify-content-between gap-2 flex-wrap">
                            <span class="md-body-large">{{ $mission->title }}</span>
                            @if ($status)<x-ui.badge>{{ $status }}</x-ui.badge>@endif
                        </div>
                        @if ($mission->source === \App\Models\Game\GameMission::SOURCE_DIRECTOR)
                            <span><x-ui.badge tone="info">Propuesta por el director</x-ui.badge></span>
                        @endif
                        <x-ui.progress :value="$mission->progress" :max="$mission->target" :label="$mission->title"
                                       :value-text="$mission->progress.'/'.$mission->target" />
                        @if ($mission->type === \App\Models\Game\GameMission::TYPE_SPECIFIC)
                            @php $done = $doneTasks[$mission->id] ?? []; @endphp
                            <ul class="game-mission__tasks" aria-label="Tareas de la misión">
                                @foreach ($mission->params['tasks'] ?? [] as $task)
                                    @php
                                        $counted = in_array($task['id'], $done, true);
                                        $exists = $existingTasks->has($task['id']);
                                        $isDone = $counted || ($exists && $existingTasks[$task['id']]);
                                    @endphp
                                    <li class="game-mission__task {{ $isDone ? 'is-done' : '' }}">
                                        <i class="bi {{ $isDone ? 'bi-check-circle-fill' : 'bi-circle' }}" aria-hidden="true"></i>
                                        @if ($task['code'])<span class="md-label-medium game-muted">{{ $task['code'] }}</span>@endif
                                        <span class="game-mission__task-title">{{ $task['title'] }}</span>
                                        <span class="visually-hidden">{{ $isDone ? 'Completada' : 'Pendiente' }}</span>
                                        @if (! $exists)
                                            <span class="md-label-small game-muted">Tarea eliminada</span>
                                        @elseif (! $isDone)
                                            <a href="{{ route('tasks.list', ['edit' => $task['id']]) }}" wire:navigate class="md-btn-text">abrir <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        @php
                            $link = match ($mission->type) {
                                'category_tasks' => ['href' => route('tasks.list', ['category' => [$mission->params['category'] ?? '']]), 'label' => 'Ver tareas'],
                                'habit_groups' => ['href' => route('habits'), 'label' => 'Ver hábitos'],
                                'variety' => ['href' => route('tasks.list'), 'label' => 'Ver tareas'],
                                default => null,
                            };
                        @endphp
                        @if ($link)
                            <div><a href="{{ $link['href'] }}" wire:navigate class="md-btn-text">{{ $link['label'] }} <i class="bi bi-arrow-right" aria-hidden="true"></i></a></div>
                        @endif
                    </li>
                @endforeach
            </x-ui.list>
        @endif
    </section>
</x-module-shell>
