@php
    $priorityBadges = [
        'urgent-important' => ['emoji' => '🔴', 'label' => 'Urgente e importante'],
        'not-urgent-important' => ['emoji' => '🟡', 'label' => 'Importante'],
        'urgent-not-important' => ['emoji' => '🟠', 'label' => 'Urgente'],
        'not-urgent-not-important' => ['emoji' => '⚪', 'label' => 'Sin urgencia'],
    ];
@endphp

<div class="md-card-elevated h-100">
    <div class="d-flex align-items-center gap-2 mb-2">
        <h2 class="md-title-small mb-0" style="color: var(--md-sys-color-on-surface);">
            <i class="bi bi-list-task" style="color: var(--md-custom-color-info);"></i> Tareas de hoy
        </h2>
        <span class="md-chip-tonal" style="background: var(--md-custom-color-info-container); color: var(--md-custom-color-on-info-container);">
            {{ $pendingTasks }} pendientes
        </span>
        <a href="{{ route('tasks.list') }}" class="md-btn-text ms-auto" style="height: 32px; padding: 0 10px;">Ver todas</a>
    </div>

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
                    <x-ui.task-code :code="$task->code" /> {{ $task->title }}
                    @if ($task->is_recurrent)
                        <i class="bi bi-arrow-repeat" style="color: var(--md-sys-color-on-surface-variant);" title="Recurrente"></i>
                    @endif
                </div>
                <div class="md-list-item-supporting">
                    @if ($isOverdue)
                        <span style="color: var(--md-sys-color-error);">Vencida · {{ $task->end_date->translatedFormat('d M') }}</span>
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
        <p class="md-body-small mb-0" style="color: var(--md-sys-color-on-surface-variant);">
            Sin tareas para este día. <a href="{{ route('tasks.planning', ['date' => $selectedDate]) }}">Planificar</a>
        </p>
    @endforelse

    @if ($completedTasksToday > 0)
        <p class="md-label-small mt-2 mb-0" style="color: var(--md-custom-color-success);">
            <i class="bi bi-check2-circle"></i> {{ $completedTasksToday }} completada{{ $completedTasksToday > 1 ? 's' : '' }} este día
        </p>
    @endif
</div>
