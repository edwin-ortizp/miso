@php
    $priorityBadges = [
        'urgent-important' => ['emoji' => '🔴', 'label' => 'Urgente e importante'],
        'not-urgent-important' => ['emoji' => '🟡', 'label' => 'Importante'],
        'urgent-not-important' => ['emoji' => '🟠', 'label' => 'Urgente'],
        'not-urgent-not-important' => ['emoji' => '⚪', 'label' => 'Sin urgencia'],
    ];
@endphp

<div class="md-card-elevated">
    <x-home.card-head icon="bi-list-task" tone="info" title="Tareas de hoy" :count="$todayTaskCount.' para hoy'"
        :href="route('tasks.list')" action="Ver todas" />

    @forelse ($todayTasks as $task)
        @php $isOverdue = $task->end_date && $task->end_date->lt(now()->startOfDay()); @endphp
        <div class="md-list-item w-100" wire:key="home-task-{{ $task->id }}">
            <button type="button"
                    class="md-list-item-leading"
                    style="border: 0; background: transparent; padding: 0; cursor: pointer;"
                    wire:click="toggleTask('{{ $task->id }}')"
                    wire:loading.attr="disabled"
                    wire:target="toggleTask('{{ $task->id }}')"
                    aria-label="Completar {{ $task->title }}">
                <div class="md-list-checkbox"></div>
            </button>
            <div class="md-list-item-content">
                <div class="md-list-item-headline">
                    @if ($task->priority && isset($priorityBadges[$task->priority]))
                        <span title="{{ $priorityBadges[$task->priority]['label'] }}">{{ $priorityBadges[$task->priority]['emoji'] }}</span>
                    @endif
                    {{ $task->title }}
                    @if ($task->is_recurrent)
                        <i class="home-muted bi bi-arrow-repeat" title="Recurrente"></i>
                    @endif
                </div>
                <div class="md-list-item-supporting">
                    <x-ui.task-code :code="$task->code" />
                    <x-ui.task-schedule-lock :locked="$task->schedule_locked && (bool) $task->end_date" />
                    @if ($isOverdue)
                        <span class="home-tone-text--error">Vencida · {{ $task->end_date->translatedFormat('d M') }}</span>
                    @elseif ($task->end_date)
                        Vence {{ $task->end_date->translatedFormat('d M') }}
                    @endif
                    @if ($task->category)
                        · {{ ucfirst($task->category) }}
                    @endif
                </div>
            </div>
        </div>
    @empty
        <p class="home-muted md-body-small mb-0">
            Sin tareas para este día. <a href="{{ route('tasks.planning', ['date' => $selectedDate]) }}">Planificar</a>
        </p>
    @endforelse

    @if ($todayTaskCount > $todayTasks->count())
        <p class="home-muted md-label-small mt-2 mb-0">Se muestran las {{ $todayTasks->count() }} más importantes de {{ $todayTaskCount }}.</p>
    @endif
    <p class="home-card-foot md-label-small">
        @if ($completedTasksToday > 0)
            <span class="home-tone-text--success"><i class="bi bi-check2-circle" aria-hidden="true"></i> {{ $completedTasksToday }} completada{{ $completedTasksToday > 1 ? 's' : '' }} este día</span>
        @endif
        <span class="home-muted">{{ $pendingTasks }} pendiente{{ $pendingTasks === 1 ? '' : 's' }} en total</span>
    </p>
</div>
