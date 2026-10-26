<x-module-shell module="tasks" x-data="{ showRecurringDialog: $wire.entangle('showRecurringCompletion') }">
    @include('livewire.task.partials.filter-bar', ['title' => 'Tareas en el Kanban', 'icon' => 'bi-kanban', 'showGrouping' => true])
    {{-- Kanban Grid --}}
    <div class="row g-3">
        @php
            $columnColors = [
                'danger' => ['bg' => 'var(--md-sys-color-error-container)', 'text' => 'var(--md-sys-color-on-error-container)', 'accent' => 'var(--md-sys-color-error)'],
                'warning' => ['bg' => 'var(--md-custom-color-warning-container)', 'text' => 'var(--md-custom-color-on-warning-container)', 'accent' => 'var(--md-custom-color-warning)'],
                'info' => ['bg' => 'var(--md-custom-color-info-container)', 'text' => 'var(--md-custom-color-on-info-container)', 'accent' => 'var(--md-custom-color-info)'],
                'secondary' => ['bg' => 'var(--md-sys-color-secondary-container)', 'text' => 'var(--md-sys-color-on-secondary-container)', 'accent' => 'var(--md-sys-color-secondary)'],
                'success' => ['bg' => 'var(--md-sys-color-primary-container)', 'text' => 'var(--md-sys-color-on-primary-container)', 'accent' => 'var(--md-sys-color-primary)'],
            ];
        @endphp
        @foreach ($columns as $key => $column)
            @php $colors = $columnColors[$column['color']] ?? $columnColors['secondary']; @endphp
            <div class="col-md-6 col-lg-3" wire:key="kanban-column-{{ $groupBy }}-{{ $key }}">
                <div class="md-card-outlined" style="height: 100%; padding: 0; overflow: hidden;">
                    <div style="padding: 12px 16px; background: {{ $colors['bg'] }}; color: {{ $colors['text'] }};">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="md-title-small">{{ $column['label'] }}</span>
                            <span class="md-chip-tonal" style="background: {{ $colors['accent'] }}; color: white;">{{ $column['tasks']->count() }}</span>
                        </div>
                    </div>
                    <div style="padding: 8px; min-height: 200px; max-height: 500px; overflow-y: auto;">
                        @forelse ($column['tasks'] as $task)
                            <div class="md-card-elevated task-kanban-card mb-2 {{ $task->completed ? 'md-list-item--completed' : '' }}" style="padding: 8px 12px;" wire:key="kanban-{{ $groupBy }}-{{ $key }}-{{ $task->id }}">
                                <div class="d-flex align-items-start gap-2">
                                    <button wire:click="toggleComplete('{{ $task->id }}')" wire:loading.attr="disabled" aria-label="{{ $task->completed ? 'Reabrir tarea: ' : 'Completar tarea: ' }}{{ $task->title }}" aria-pressed="{{ $task->completed ? 'true' : 'false' }}" class="md-list-checkbox {{ $task->completed ? 'checked' : '' }}" style="width: 20px; height: 20px; margin-top: 2px;">
                                        @if ($task->completed)
                                            <i class="bi bi-check" style="font-size: 0.7rem;"></i>
                                        @endif
                                    </button>
                                    <div style="min-width: 0;">
                                        <span class="md-body-small {{ $task->completed ? '' : 'fw-medium' }}" style="color: var(--md-sys-color-on-surface);">
                                            <x-ui.task-code :code="$task->code" /> {{ $task->title }}
                                        </span>
                                        @if ($task->category)
                                            <div class="task-kanban-card__category"><x-ui.catalog-chip :color="$this->categoryColor($task->category)" icon="bi-tag" class="task-kanban-category">{{ $categories[$task->category] ?? $task->category }}</x-ui.catalog-chip></div>
                                        @endif
                                    </div>
                                </div>
                                @php $step = $statusSteps[$task->status_id] ?? null; @endphp
                                <footer class="task-kanban-card__actions" aria-label="Estado de {{ $task->title }}">
                                    <button type="button" class="md-btn-icon md-btn--sm"
                                            wire:click="moveStatus('{{ $task->id }}', 'previous')" wire:loading.attr="disabled"
                                            @disabled(! ($step['previous'] ?? null))
                                            aria-label="{{ isset($step['previous']) ? 'Mover a '.$step['previous']->name : 'Sin estado anterior' }}"
                                            title="{{ isset($step['previous']) ? 'Anterior: '.$step['previous']->name : 'Sin estado anterior' }}">
                                        <i class="bi bi-arrow-left" aria-hidden="true"></i>
                                    </button>
                                    <span class="task-kanban-card__status" title="{{ $step['current']->name ?? 'Sin estado' }}">{{ $step['current']->name ?? 'Sin estado' }}</span>
                                    <button type="button" class="md-btn-icon md-btn--sm"
                                            wire:click="moveStatus('{{ $task->id }}', 'next')" wire:loading.attr="disabled"
                                            @disabled(! ($step['next'] ?? null))
                                            aria-label="{{ isset($step['next']) ? 'Mover a '.$step['next']->name : 'Sin estado siguiente' }}"
                                            title="{{ isset($step['next']) ? 'Siguiente: '.$step['next']->name : 'Sin estado siguiente' }}">
                                        <i class="bi bi-arrow-right" aria-hidden="true"></i>
                                    </button>
                                </footer>
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
