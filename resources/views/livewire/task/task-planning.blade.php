<x-module-shell module="tasks" x-data="{ showDialog: $wire.entangle('showForm'), showRecurringDialog: $wire.entangle('showRecurringCompletion') }">
    @php
        $definitions = [
            'overdue' => ['Atrasadas', 'bi-exclamation-circle', 'is-overdue'],
            'today' => ['Hoy', 'bi-calendar-check', 'is-today'],
            'tomorrow' => ['Mañana', 'bi-calendar-plus', 'is-tomorrow'],
            'future' => ['Futuras', 'bi-calendar-range', 'is-future'],
            'no-date' => ['Sin fecha', 'bi-calendar-x', 'is-no-date'],
        ];
    @endphp

    @include('livewire.task.partials.filter-bar', ['title' => 'Tareas en planificación', 'icon' => 'bi-calendar-week'])
    <div class="md-planning-board" aria-label="Tablero de planificación de tareas">
        @foreach ($definitions as $key => [$label, $icon, $class])
            <section class="md-planning-column {{ $class }}">
                <header class="md-planning-column-header">
                    <span><i class="bi {{ $icon }}"></i> {{ $label }}</span>
                    <span class="md-chip-tonal">{{ $columns[$key]->count() }}</span>
                </header>
                <div class="md-planning-column-content">
                    @forelse ($columns[$key] as $task)
                        @php($scheduledDate = $task->start_date ?? $task->end_date)
                        <article class="md-planning-card" wire:key="planning-{{ $task->id }}">
                            <button wire:click="openForm('{{ $task->id }}')" class="md-planning-card-main" aria-label="Abrir tarea: {{ $task->title }}">
                                <span class="md-planning-card-title"><x-ui.task-code :code="$task->code" /> {{ $task->title }}</span>
                                <span class="md-planning-card-meta">
                                    @if ($task->category)<span class="md-chip-tonal">{{ $categories[$task->category] ?? $task->category }}</span>@endif
                                    @if ($task->priority)<span class="md-planning-priority {{ $task->priority }}"></span>@endif
                                    @if ($scheduledDate)<span><i class="bi bi-calendar3"></i> {{ $scheduledDate->format('d M, H:i') }}</span>@endif
                                    @if ($task->estimated_time)<span><i class="bi bi-clock"></i> {{ $task->estimated_time_label }}</span>@endif
                                </span>
                            </button>
                            <footer class="md-planning-card-actions">
                                <button wire:click.stop="toggleComplete('{{ $task->id }}')" class="md-btn-icon" title="Completar" aria-label="Completar {{ $task->title }}"><i class="bi bi-check2"></i></button>
                                <span class="md-planning-action-divider"></span>
                                <button wire:click.stop="moveToDay('{{ $task->id }}', 0)" class="md-btn-icon" title="Asignar para hoy" aria-label="Asignar para hoy"><i class="bi bi-calendar-check"></i></button>
                                <button wire:click.stop="moveToDay('{{ $task->id }}', 1)" class="md-btn-icon" title="Asignar para mañana" aria-label="Asignar para mañana"><i class="bi bi-calendar-plus"></i></button>
                                <button wire:click.stop="moveToDay('{{ $task->id }}', 2)" class="md-btn-icon" title="Asignar para pasado mañana" aria-label="Asignar para pasado mañana"><i class="bi bi-calendar2-plus"></i></button>
                                <button wire:click.stop="clearDates('{{ $task->id }}')" class="md-btn-icon" title="Quitar fechas" aria-label="Quitar fechas"><i class="bi bi-calendar-x"></i></button>
                            </footer>
                        </article>
                    @empty
                        <p class="md-planning-empty">Sin tareas pendientes</p>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>

    <template x-if="showDialog">
        <div>
            <div class="md-dialog-scrim" wire:click="closeForm"></div>
            <div class="md-dialog md-dialog--wide" @click.stop>
                <h2 class="md-dialog-headline md-headline-small">Editar tarea</h2>
                <div class="md-dialog-content"><div class="d-flex flex-column gap-3">
                    <div class="md-text-field"><input type="text" wire:model="title" placeholder=" " id="planning-task-title"><label for="planning-task-title">Título</label></div>
                    @include('partials.markdown-editor', ['model' => 'description', 'mode' => 'descriptionMode', 'modeValue' => $descriptionMode, 'content' => $description, 'id' => 'planning-task-description', 'placeholder' => 'Detalles de la tarea. Admite Markdown.'])
                    <div class="row g-3">
                        <div class="col-md-4"><div class="md-text-field"><select wire:model="category" id="planning-task-category"><option value="">Sin categoría</option>@foreach ($categories as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select><label for="planning-task-category">Categoría</label></div></div>
                        <div class="col-md-4"><div class="md-text-field"><select wire:model="priority" id="planning-task-priority"><option value="">Sin prioridad</option>@foreach ($priorities as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select><label for="planning-task-priority">Prioridad</label></div></div>
                        <div class="col-md-4"><div class="md-text-field"><select wire:model="size" id="planning-task-size"><option value="">Sin tamaño</option>@foreach ($sizes as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select><label for="planning-task-size">Tamaño</label></div></div>
                    </div>
                    @include('livewire.task.partials.schedule-fields', ['idPrefix' => 'planning-task', 'startModel' => 'startDate', 'startTimeModel' => 'startTime', 'endModel' => 'endDate', 'endTimeModel' => 'endTime', 'durationAction' => 'applyDuration'])
                    <label class="md-checkbox"><input type="checkbox" wire:model="isPrivate"><i class="bi bi-lock"></i> Tarea privada</label>
                </div></div>
                <div class="md-dialog-actions"><button wire:click="closeForm" class="md-btn-text">Cancelar</button><button wire:click="save" class="md-btn-filled"><i class="bi bi-check-lg"></i> Actualizar</button></div>
            </div>
        </div>
    </template>

    @include('livewire.task.partials.recurring-completion-dialog')
</x-module-shell>
