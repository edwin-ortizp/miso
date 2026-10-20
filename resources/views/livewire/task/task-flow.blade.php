<x-module-shell module="tasks" x-data="{ showDialog: $wire.entangle('showForm'), showRecurringDialog: $wire.entangle('showRecurringCompletion') }">
    @include('livewire.task.partials.filter-bar', ['title' => 'Tareas en el flujo', 'icon' => 'bi-signpost-split'])
<section class="task-flow-page">
    @forelse ($lanes as $lane)
        <section class="task-flow-lane" wire:key="flow-lane-{{ $lane['laneKey'] }}" aria-labelledby="flow-lane-{{ $lane['laneKey'] }}">
            <header class="task-flow-lane-header">
                <div>
                    <p class="task-flow-lane-kicker">Carril</p>
                    <h2 id="flow-lane-{{ $lane['laneKey'] }}">{{ $lane['label'] }}</h2>
                </div>
                <div class="task-flow-lane-counts">
                    <span class="md-chip-tonal md-chip-tonal--primary">{{ $lane['pendingTotal'] }} pendientes</span>
                    @if ($lane['completedTotal'])
                        <span class="md-chip-tonal">{{ $lane['completedTotal'] }} completadas</span>
                    @endif
                </div>
            </header>

            @if ($lane['pendingTotal'])
                <div class="task-flow-scroll" tabindex="0" aria-label="Secuencia pendiente de {{ $lane['label'] }}">
                    <ol class="task-flow-track">
                        @foreach ($lane['tasks'] as $task)
                            <li class="task-flow-step" wire:key="flow-task-{{ $task->id }}">
                                <article class="task-flow-card">
                                    <button wire:click="openForm('{{ $task->id }}')" class="task-flow-card-main" aria-label="Editar tarea: {{ $task->title }}">
                                        <span class="task-flow-position">{{ $loop->iteration }}</span>
                                        <span class="task-flow-card-content">
                                            <span class="task-flow-card-title">{{ $task->title }}</span>
                                            @if ($task->size || $task->estimated_time)
                                                <span class="task-flow-card-meta">
                                                    @if ($task->size)<span>{{ $task->size }}</span>@endif
                                                    @if ($task->estimated_time)<span>{{ $task->estimated_time_label }}</span>@endif
                                                </span>
                                            @endif
                                        </span>
                                    </button>
                                    <div class="task-flow-card-actions">
                                        <button wire:click="toggleComplete('{{ $task->id }}')" class="md-btn-icon" title="Completar" aria-label="Completar {{ $task->title }}"><i class="bi bi-check-lg"></i></button>
                                        <button wire:click="movePrevious('{{ $task->id }}')" @disabled($loop->first) class="md-btn-icon" title="Mover antes" aria-label="Mover {{ $task->title }} antes"><i class="bi bi-arrow-left"></i></button>
                                        <button wire:click="moveNext('{{ $task->id }}')" @disabled($loop->last && $lane['pendingTotal'] === $lane['tasks']->count()) class="md-btn-icon" title="Mover después" aria-label="Mover {{ $task->title }} después"><i class="bi bi-arrow-right"></i></button>
                                    </div>
                                </article>
                            </li>
                        @endforeach
                    </ol>
                </div>

                @if ($lane['pendingTotal'] > $lane['tasks']->count())
                    <div class="task-flow-more">
                        <button wire:click="loadMore('{{ $lane['laneKey'] }}')" class="md-btn md-btn-outlined"><i class="bi bi-plus-lg"></i> Ver 30 tareas pendientes más</button>
                    </div>
                @endif
            @endif

            @if ($lane['completedTotal'])
                <details class="task-flow-history">
                    <summary>
                        <span><i class="bi bi-clock-history"></i> Historial del carril</span>
                        <span>{{ $lane['completedTotal'] }} completadas</span>
                    </summary>
                    <ol class="task-flow-history-list">
                        @foreach ($lane['completedHistory'] as $task)
                            <li class="task-flow-history-item" wire:key="flow-history-task-{{ $task->id }}">
                                <button wire:click="openForm('{{ $task->id }}')" class="task-flow-history-main" aria-label="Editar tarea completada: {{ $task->title }}">
                                    <i class="bi bi-check2-circle"></i>
                                    <span>{{ $task->title }}</span>
                                    @if ($task->completed_at)<time datetime="{{ $task->completed_at->toDateString() }}">{{ $task->completed_at->translatedFormat('d M Y') }}</time>@endif
                                </button>
                                <button wire:click="toggleComplete('{{ $task->id }}')" class="md-btn-icon" title="Marcar pendiente" aria-label="Marcar pendiente {{ $task->title }}"><i class="bi bi-arrow-counterclockwise"></i></button>
                            </li>
                        @endforeach
                    </ol>
                </details>
            @endif
        </section>
    @empty
        <div class="md-card-filled task-flow-empty">
            <i class="bi bi-signpost-2"></i>
            <p class="md-title-medium mb-1">Aún no hay tareas para mostrar</p>
            <p class="mb-0">Crea una tarea pendiente o completa una para empezar a construir su historial.</p>
        </div>
    @endforelse
</section>

@include('livewire.task.partials.edit-task-dialog', ['dialogId' => 'flow-task', 'showCompletionAction' => true])
</x-module-shell>
