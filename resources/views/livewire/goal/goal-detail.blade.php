<x-module-shell module="goals" :back="['href' => route('goals'), 'label' => 'Volver a objetivos']">
    <x-slot:actions>
        <x-module-actions :primary="['label' => 'Agregar tarea', 'icon' => 'bi-check2-square', 'action' => 'openTaskForm']" :secondary="array_values(array_filter([
            ['label' => 'Registrar avance', 'icon' => 'bi-journal-plus', 'action' => 'openEntryForm', 'create' => true],
            $kpi ? ['label' => 'Registrar medición', 'icon' => 'bi-graph-up-arrow', 'action' => 'openNumericForm', 'create' => true] : null,
            ['label' => 'Editar objetivo', 'icon' => 'bi-pencil', 'action' => 'openGoalForm'],
        ]))" />
    </x-slot:actions>

    <div class="goal-detail">
        <section class="goal-hero md-card-outlined">
            <div>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="md-chip-tonal {{ $goal->status === 'completed' ? 'goal-status--completed' : ($goal->status === 'abandoned' ? 'goal-status--abandoned' : '') }}">
                        <i class="bi {{ $goal->status === 'completed' ? 'bi-check-circle-fill' : ($goal->status === 'abandoned' ? 'bi-x-circle-fill' : 'bi-flag-fill') }}"></i>
                        {{ $goal->status === 'completed' ? 'Completado' : ($goal->status === 'abandoned' ? 'Abandonado' : 'Activo') }}
                    </span>
                    @if ($goal->start_date && $goal->due_date)
                        <span class="goal-hero__date">{{ $goal->start_date->format('d M') }} — {{ $goal->due_date->format('d M Y') }}</span>
                    @endif
                </div>
                <h2>{{ $goal->title }}</h2>
                @if ($goal->description)<p>{{ $goal->description }}</p>@endif
            </div>
            <div class="goal-hero__state" aria-label="Cambiar estado">
                @foreach (['active' => 'Activo', 'completed' => 'Completar', 'abandoned' => 'Abandonar'] as $value => $label)
                    @if ($goal->status !== $value)
                        <button wire:click="updateStatus('{{ $value }}')" class="md-btn-text">{{ $label }}</button>
                    @endif
                @endforeach
            </div>
        </section>

        <div class="goal-detail__grid">
            <main class="goal-detail__main">
                @if ($kpi && $progress)
                    @php($expectedPercent = $progress['expectedPercent'] ?? 0)
                    <section class="goal-kpi-card md-card-outlined">
                        <div class="goal-section-heading">
                            <div><span class="goal-section-heading__eyebrow">KPI principal</span><h2>{{ $kpi['name'] }}</h2></div>
                            
                        </div>

                        <div class="goal-kpi-values">
                            <div><span>Avance real</span><strong>{{ number_format($progress['currentValue'], 2, ',', '.') }} <small>{{ $kpi['unit'] }}</small></strong><em>{{ number_format($progress['actualPercent'], 0) }}%</em></div>
                            <div><span>Esperado hoy</span><strong>{{ $progress['expectedValue'] === null ? '—' : number_format($progress['expectedValue'], 2, ',', '.') }} @if($progress['expectedValue'] !== null)<small>{{ $kpi['unit'] }}</small>@endif</strong><em>{{ $progress['expectedPercent'] === null ? 'Sin calendario' : number_format($progress['expectedPercent'], 0).'%' }}</em></div>
                            <div><span>Meta final</span><strong>{{ number_format($progress['targetValue'], 2, ',', '.') }} <small>{{ $kpi['unit'] }}</small></strong><em>{{ $kpi['direction'] === 'decrease' ? 'Reducir' : 'Aumentar' }}</em></div>
                        </div>
                        <div class="goal-progress-track" aria-label="{{ number_format($progress['actualPercent'], 0) }}% de avance real y {{ number_format($expectedPercent, 0) }}% esperado">
                            <i class="goal-progress-track__expected" style="width: {{ $expectedPercent }}%"></i>
                            <b style="width: {{ $progress['actualPercent'] }}%"></b>
                        </div>
                        <div class="goal-progress-legend"><span><i class="goal-progress-legend__actual"></i> Avance real</span><span><i class="goal-progress-legend__expected"></i> Ritmo esperado</span><strong class="{{ $progress['onSchedule'] === null ? '' : ($progress['onSchedule'] ? 'is-on-track' : 'is-behind') }}">{{ $progress['onSchedule'] === null ? 'Añade fechas para calcular el ritmo' : ($progress['onSchedule'] ? 'Vas al ritmo esperado' : 'Requiere atención') }}</strong></div>

                        <div class="goal-history">
                            <div class="goal-history__header"><h3>Historial de mediciones</h3><span>{{ $goal->goalNumericEntries->count() }} registros</span></div>
                            @forelse ($goal->goalNumericEntries as $entry)
                                <div class="goal-history__row" wire:key="numeric-{{ $entry->id }}">
                                    <div><strong>{{ number_format($entry->value, 2, ',', '.') }} {{ $kpi['unit'] }}</strong><span>{{ $entry->note ?: 'Sin nota' }}</span></div>
                                    <time>{{ $entry->date->format('d M Y') }}</time>
                                    <div class="goal-history__actions"><button wire:click="openNumericForm('{{ $entry->id }}')" class="md-btn-icon" aria-label="Editar medición"><i class="bi bi-pencil"></i></button><button wire:click="deleteNumericEntry('{{ $entry->id }}')" wire:confirm="¿Eliminar esta medición?" class="md-btn-icon" aria-label="Eliminar medición"><i class="bi bi-trash"></i></button></div>
                                </div>
                            @empty
                                <p class="goal-history__empty">Aún no hay mediciones. Registra la primera para actualizar el avance real.</p>
                            @endforelse
                        </div>
                    </section>
                @else
                    <section class="goal-kpi-empty md-card-outlined">
                        <i class="bi bi-graph-up-arrow"></i><div><h2>Este objetivo aún no tiene KPI</h2><p>Define el indicador, el valor inicial y la meta para comparar tu avance con el ritmo esperado.</p></div><button wire:click="openGoalForm" class="md-btn-filled">Configurar KPI</button>
                    </section>
                @endif

                <section class="md-card-outlined goal-section-card">
                    <div class="goal-section-heading"><div><span class="goal-section-heading__eyebrow">Bitácora</span><h2>Avances e hitos</h2></div></div>
                    <div class="goal-timeline">
                        @forelse ($goal->goalEntries as $entry)
                            <article class="goal-timeline__item {{ $entry->is_milestone ? 'is-milestone' : '' }}" wire:key="entry-{{ $entry->id }}">
                                <div class="goal-timeline__mark"><i class="bi {{ $entry->is_milestone ? 'bi-star-fill' : 'bi-dot' }}"></i></div><div class="goal-timeline__content"><time>{{ $entry->date->translatedFormat('d \d\e F, Y') }}</time><p>{{ $entry->text }}</p></div><div class="goal-history__actions"><button wire:click="openEntryForm('{{ $entry->id }}')" class="md-btn-icon" aria-label="Editar avance"><i class="bi bi-pencil"></i></button><button wire:click="deleteEntry('{{ $entry->id }}')" wire:confirm="¿Eliminar este avance?" class="md-btn-icon" aria-label="Eliminar avance"><i class="bi bi-trash"></i></button></div>
                            </article>
                        @empty
                            <p class="goal-history__empty">Registra avances o hitos para dejar una historia útil del objetivo.</p>
                        @endforelse
                    </div>
                </section>

                <section class="md-card-outlined goal-section-card">
                    <div class="goal-section-heading"><div><span class="goal-section-heading__eyebrow">Ejecución</span><h2>Tareas relacionadas</h2></div></div>
                    <div class="goal-task-list">
                        @forelse ($goal->tasks as $task)
                            <a href="{{ route('tasks.list', ['edit' => $task->id]) }}" class="goal-task-row" wire:navigate wire:key="task-{{ $task->id }}"><i class="bi {{ $task->completed ? 'bi-check-circle-fill' : 'bi-circle' }}"></i><span><x-ui.task-code :code="$task->code" /> {{ $task->title }}</span>@if ($task->end_date)<time>{{ $task->end_date->format('d M') }}</time>@endif<i class="bi bi-arrow-up-right"></i></a>
                        @empty
                            <p class="goal-history__empty">Aún no hay tareas vinculadas. Crea la primera acción concreta para este objetivo.</p>
                        @endforelse
                    </div>
                </section>
            </main>

            <aside class="goal-detail__rail">
                <section class="md-context-widget"><div class="md-context-widget__header"><span class="md-context-widget__icon"><i class="bi bi-clipboard-data"></i></span><h2>Panorama</h2></div><div class="md-context-widget__content"><dl class="md-context-list"><div><dt>Tareas</dt><dd>{{ $goal->tasks->where('completed', true)->count() }}/{{ $goal->tasks->count() }}</dd></div><div><dt>Avances</dt><dd>{{ $goal->goalEntries->count() }}</dd></div><div><dt>Hitos</dt><dd>{{ $goal->goalEntries->where('is_milestone', true)->count() }}</dd></div>@if($goal->due_date)<div><dt>Fecha límite</dt><dd>{{ $goal->due_date->format('d M Y') }}</dd></div>@endif</dl></div></section>
                <section class="md-context-widget goal-motivation"><div class="md-context-widget__header"><span class="md-context-widget__icon"><i class="bi bi-lightning-charge"></i></span><h2>Motivación</h2></div><div class="md-context-widget__content"><p>Marca cómo te está haciendo sentir este objetivo.</p><div><button wire:click="incrementMotivation('positive')" class="md-btn-outlined"><i class="bi bi-hand-thumbs-up"></i> {{ $goal->positive_count }}</button><button wire:click="incrementMotivation('negative')" class="md-btn-outlined"><i class="bi bi-hand-thumbs-down"></i> {{ $goal->negative_count }}</button></div></div></section>
            </aside>
        </div>
    </div>

    <x-ui.form-dialog :open="$showGoalForm" close="$set('showGoalForm', false)" submit-action="saveGoal" id="goal-edit-dialog"
                      title="Editar objetivo" icon="bi-flag" submit="Guardar cambios"
                      :sections="[
                          'basic' => ['label' => 'Información básica', 'icon' => 'bi-flag', 'error' => $errors->hasAny(['title', 'startDate', 'dueDate'])],
                          'reminders' => ['label' => 'Recordatorios', 'icon' => 'bi-bell', 'error' => $errors->hasAny(['reminders', 'reminders.*'])],
                          'kpi' => ['label' => 'Indicador (KPI)', 'icon' => 'bi-graph-up-arrow', 'error' => $errors->hasAny(['kpiName', 'kpiUnit', 'kpiStartValue', 'kpiTargetValue'])],
                      ]">
        <x-ui.form-dialog-section name="basic" title="Información básica">
            <div class="d-flex flex-column gap-3">
                <x-ui.field name="title" label="Título" :required="true" wire:model="title" />
                <x-ui.textarea name="description" label="Descripción" rows="3" wire:model="description" />
                <x-ui.select name="status" label="Estado" :selected="$status"
                             :options="['active' => 'Activo', 'completed' => 'Completado', 'abandoned' => 'Abandonado']" wire:model="status" />
                <div class="md-field-pair">
                    <x-ui.field name="startDate" label="Fecha inicio" type="date" wire:model="startDate" />
                    <x-ui.field name="dueDate" label="Fecha límite" type="date" wire:model="dueDate" />
                </div>
            </div>
        </x-ui.form-dialog-section>
        <x-ui.form-dialog-section name="reminders" title="Recordatorios">
            @include('livewire.notifications.reminder-fields')
        </x-ui.form-dialog-section>
        <x-ui.form-dialog-section name="kpi" title="Indicador (KPI)">
            <div class="d-flex flex-column gap-3">
                <label class="goal-kpi-toggle"><input wire:model.live="kpiEnabled" type="checkbox"><span><i class="bi bi-graph-up-arrow" aria-hidden="true"></i> Activar KPI único</span></label>
                @if ($kpiEnabled)
                    <div class="goal-kpi-form">
                        <x-ui.field name="kpiName" label="Nombre del KPI" wire:model="kpiName" />
                        <x-ui.field name="kpiUnit" label="Unidad" wire:model="kpiUnit" />
                        <x-ui.select name="kpiDirection" label="Dirección" :selected="$kpiDirection"
                                     :options="['increase' => 'Aumentar hasta la meta', 'decrease' => 'Reducir hasta la meta']" wire:model="kpiDirection" />
                        <div class="md-field-pair">
                            <x-ui.field name="kpiStartValue" label="Valor inicial" type="number" step="0.01" wire:model="kpiStartValue" />
                            <x-ui.field name="kpiTargetValue" label="Valor objetivo" type="number" step="0.01" wire:model="kpiTargetValue" />
                        </div>
                    </div>
                @endif
            </div>
        </x-ui.form-dialog-section>
    </x-ui.form-dialog>

    @include('livewire.goal.progress-dialogs')

    <x-ui.form-dialog :open="$showTaskForm" close="$set('showTaskForm', false)" submit-action="saveTask" id="goal-task-dialog"
                      title="Agregar tarea relacionada" icon="bi-check2-square" submit="Crear y vincular"
                      :sections="[
                          'basic' => ['label' => 'Información básica', 'icon' => 'bi-check2-square', 'error' => $errors->has('taskTitle')],
                          'details' => ['label' => 'Detalles adicionales', 'icon' => 'bi-sliders'],
                      ]">
        <x-ui.form-dialog-section name="basic" title="Información básica">
            <div class="d-flex flex-column gap-3">
                <x-ui.field name="taskTitle" label="Título" :required="true" wire:model="taskTitle" />
                <x-ui.textarea name="taskDescription" label="Descripción" rows="3" wire:model="taskDescription" />
                <x-ui.field name="taskDueDate" label="Fecha límite" type="date" wire:model="taskDueDate" />
            </div>
        </x-ui.form-dialog-section>
        <x-ui.form-dialog-section name="details" title="Detalles adicionales">
            <div class="md-field-trio">
                <x-ui.select name="taskCategory" label="Categoría" placeholder="Sin categoría" :options="$categories" :selected="$taskCategory" wire:model="taskCategory" />
                <x-ui.select name="taskPriority" label="Prioridad" placeholder="Sin prioridad" :options="$priorities" :selected="$taskPriority" wire:model="taskPriority" />
                <x-ui.select name="taskSize" label="Tamaño" placeholder="Sin tamaño" :options="$sizes" :selected="$taskSize" wire:model="taskSize" />
            </div>
        </x-ui.form-dialog-section>
    </x-ui.form-dialog>
</x-module-shell>
