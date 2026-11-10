@props(['missions', 'doneTasks', 'existingTasks', 'adventure' => false])
<x-ui.list label="Misiones de la semana" :class="$adventure ? 'game-adventure__mission-list' : ''">
    @foreach ($missions as $mission)
        @php
            $status = $mission->full_rewarded_at ? '¡Misión cumplida!' : ($mission->half_rewarded_at ? 'Ball recibida' : null);
            $kind = match ($mission->type) {
                'habit_groups' => ['key' => 'habits', 'label' => 'Ritual diario', 'icon' => 'bi-sun', 'unit' => 'días'],
                'variety' => ['key' => 'explore', 'label' => 'Exploración', 'icon' => 'bi-compass', 'unit' => 'ámbitos'],
                default => ['key' => 'tasks', 'label' => 'Pequeñas conquistas', 'icon' => 'bi-flag', 'unit' => 'tareas'],
            };
            $questState = $mission->full_rewarded_at ? 'complete' : ($mission->half_rewarded_at ? 'rewarded' : ($mission->progress > 0 ? 'active' : 'new'));
            $questLabel = match ($questState) {
                'complete' => '¡Misión cumplida!', 'rewarded' => 'Recompensa parcial recibida',
                'active' => 'En marcha', default => 'Por comenzar',
            };
            $link = match ($mission->type) {
                'category_tasks' => ['href' => route('tasks.list', ['category' => [$mission->params['category'] ?? '']]), 'label' => 'Ver tareas'],
                'habit_groups' => ['href' => route('habits'), 'label' => 'Ver hábitos'],
                'variety' => ['href' => route('tasks.list'), 'label' => 'Ver tareas'],
                default => $adventure && $mission->type === 'specific_tasks' ? ['href' => route('tasks.list'), 'label' => 'Ver tareas'] : null,
            };
        @endphp
        <li class="{{ $adventure ? 'game-quest game-quest--'.$kind['key'].' game-quest--'.$questState : 'game-mission' }}" wire:key="mission-{{ $mission->id }}">
            @if ($adventure)
                <div class="game-quest__art" aria-hidden="true">
                    <span class="game-quest__spark game-quest__spark--one">✦</span>
                    <span class="game-quest__emblem"><i class="bi {{ $kind['icon'] }}"></i></span>
                    <span class="game-quest__spark game-quest__spark--two">✧</span>
                    <span class="game-quest__number">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                </div>
                <div class="game-quest__body">
                    <div class="game-quest__caption">
                        <span>{{ $kind['label'] }}</span>
                        @if ($mission->source === \App\Models\Game\GameMission::SOURCE_DIRECTOR)
                            <span title="Propuesta por el director">Del director</span>
                        @endif
                    </div>
                    <h3 id="quest-title-{{ $mission->id }}" class="game-quest__title">{{ $mission->title }}</h3>
                    <span class="game-quest__state"><i class="bi {{ $questState === 'complete' ? 'bi-patch-check-fill' : ($questState === 'new' ? 'bi-circle' : 'bi-stars') }}" aria-hidden="true"></i> {{ $questLabel }}</span>
                    <div class="game-quest__progress" role="progressbar" aria-labelledby="quest-title-{{ $mission->id }}"
                         aria-valuemin="0" aria-valuemax="{{ $mission->target }}" aria-valuenow="{{ min($mission->progress, $mission->target) }}"
                         aria-valuetext="{{ $mission->progress }} de {{ $mission->target }} {{ $kind['unit'] }}">
                        <div class="game-quest__counter"><strong>{{ $mission->progress }}<span>/{{ $mission->target }}</span></strong><span>{{ $kind['unit'] }}</span></div>
                        @if ($mission->target <= 6)
                            <div class="game-quest__steps" aria-hidden="true">
                                @for ($step = 1; $step <= $mission->target; $step++)
                                    <span class="{{ $step <= $mission->progress ? 'is-filled' : '' }}"><i class="bi {{ $step <= $mission->progress ? 'bi-check-lg' : 'bi-circle' }}"></i></span>
                                @endfor
                            </div>
                        @else
                            <progress class="game-quest__bar" value="{{ $mission->progress }}" max="{{ $mission->target }}" aria-hidden="true"></progress>
                        @endif
                    </div>
            @else
                <div class="d-flex justify-content-between gap-2 flex-wrap">
                    <span class="md-body-large">{{ $mission->title }}</span>
                    @if ($status)<x-ui.badge>{{ $status }}</x-ui.badge>@endif
                </div>
                @if ($mission->source === \App\Models\Game\GameMission::SOURCE_DIRECTOR)
                    <span><x-ui.badge tone="info">Propuesta por el director</x-ui.badge></span>
                @endif
                <x-ui.progress :value="$mission->progress" :max="$mission->target" :label="$mission->title" :value-text="$mission->progress.'/'.$mission->target" />
            @endif
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

            @if ($adventure)
                    <div class="game-quest__rewards" aria-label="Recompensas de la misión">
                        @foreach ([
                            ['label' => 'Mitad', 'amount' => config('game.mission_rewards.half'), 'at' => $mission->halfThreshold(), 'received' => $mission->half_rewarded_at],
                            ['label' => 'Completa', 'amount' => config('game.mission_rewards.full'), 'at' => $mission->target, 'received' => $mission->full_rewarded_at],
                        ] as $reward)
                            <div class="game-quest__reward {{ $reward['received'] ? 'is-received' : '' }}">
                                <div class="game-quest__prize"><x-game.ball /><strong>+{{ $reward['amount'] }}</strong></div>
                                <span>{{ $reward['label'] }}</span>
                                <small>{{ $reward['received'] ? 'Recibida' : 'Al llegar a '.$reward['at'].'/'.$mission->target }}</small>
                                @if ($reward['received'])<i class="bi bi-check-circle-fill game-quest__reward-check" aria-hidden="true"></i>@endif
                            </div>
                        @endforeach
                    </div>
                    <div class="game-quest__action">
                        @if ($link)
                            <a href="{{ $link['href'] }}" wire:navigate class="md-btn-filled">{{ $link['label'] }} <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                        @else
                            <span class="md-body-small game-muted">Elige una tarea de esta misión para avanzar.</span>
                        @endif
                    </div>
                </div>
            @elseif ($link)
                <div><a href="{{ $link['href'] }}" wire:navigate class="md-btn-text">{{ $link['label'] }} <i class="bi bi-arrow-right" aria-hidden="true"></i></a></div>
            @endif
        </li>
    @endforeach
</x-ui.list>
