<x-module-shell module="tasks" x-data="{ showRecurringDialog: $wire.entangle('showRecurringCompletion') }">
    @include('livewire.task.partials.filter-bar', ['title' => 'Tareas en el Kanban', 'icon' => 'bi-kanban'])
    {{-- Kanban Grid --}}
    <div class="row g-3">
        @php
            $columnColors = [
                'urgent-important' => ['bg' => 'var(--md-sys-color-error-container)', 'text' => 'var(--md-sys-color-on-error-container)', 'accent' => 'var(--md-sys-color-error)'],
                'not-urgent-important' => ['bg' => 'var(--md-custom-color-warning-container)', 'text' => 'var(--md-custom-color-on-warning-container)', 'accent' => 'var(--md-custom-color-warning)'],
                'urgent-not-important' => ['bg' => 'var(--md-custom-color-info-container)', 'text' => 'var(--md-custom-color-on-info-container)', 'accent' => 'var(--md-custom-color-info)'],
                'not-urgent-not-important' => ['bg' => 'var(--md-sys-color-secondary-container)', 'text' => 'var(--md-sys-color-on-secondary-container)', 'accent' => 'var(--md-sys-color-secondary)'],
            ];
        @endphp
        @foreach ($columns as $key => $column)
            @php $colors = $columnColors[$key] ?? $columnColors['not-urgent-not-important']; @endphp
            <div class="col-md-6 col-lg-3">
                <div class="md-card-outlined" style="height: 100%; padding: 0; overflow: hidden;">
                    <div style="padding: 12px 16px; background: {{ $colors['bg'] }}; color: {{ $colors['text'] }};">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="md-title-small">{{ $column['label'] }}</span>
                            <span class="md-chip-tonal" style="background: {{ $colors['accent'] }}; color: white;">{{ $column['tasks']->count() }}</span>
                        </div>
                    </div>
                    <div style="padding: 8px; min-height: 200px; max-height: 500px; overflow-y: auto;">
                        @forelse ($column['tasks'] as $task)
                            <div class="md-card-elevated mb-2 {{ $task->completed ? 'md-list-item--completed' : '' }}" style="padding: 8px 12px;" wire:key="kanban-{{ $task->id }}">
                                <div class="d-flex align-items-start gap-2">
                                    <button wire:click="toggleComplete('{{ $task->id }}')" class="md-list-checkbox {{ $task->completed ? 'checked' : '' }}" style="width: 20px; height: 20px; margin-top: 2px;">
                                        @if ($task->completed)
                                            <i class="bi bi-check" style="font-size: 0.7rem;"></i>
                                        @endif
                                    </button>
                                    <div style="min-width: 0;">
                                        <span class="md-body-small {{ $task->completed ? '' : 'fw-medium' }}" style="color: var(--md-sys-color-on-surface);">
                                            {{ $task->title }}
                                        </span>
                                        @if ($task->category)
                                            <br><span class="md-chip-tonal" style="font-size: 0.5625rem; height: 18px; padding: 0 6px;">{{ $task->category }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-3" style="color: var(--md-sys-color-on-surface-variant);">
                                <span class="md-body-small">Sin tareas</span>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @include('livewire.task.partials.recurring-completion-dialog')
</x-module-shell>
