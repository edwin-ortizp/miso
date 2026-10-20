@php
    $localEditor = $localEditor ?? false;
    $bulkEditor = $bulkEditor ?? false;
@endphp
<x-ui.form-dialog :open="$showForm" :state="$localEditor ? 'showDialog' : null"
    :id="$dialogId" :title="$dialogTitle ?? 'Editar tarea'" icon="bi-list-task" module="tasks"
    class="task-properties-dialog" close="closeForm" submit-action="save"
    :validation-state="$localEditor ? 'submitted' : null"
    :title-expression="$localEditor && ! $bulkEditor ? '$wire.editingId ? \'Editar tarea\' : \'Nueva tarea\'' : null"
    :sections="[
        'basic' => ['label' => 'Información básica', 'icon' => 'bi-file-earmark-text', 'error' => $errors->hasAny(['title', 'bulkTitles'])],
        'privacy' => ['label' => 'Privacidad', 'icon' => 'bi-lock'],
        'schedule' => ['label' => 'Programación', 'icon' => 'bi-calendar3', 'error' => $errors->hasAny(['startDate', 'endDate', 'startTime', 'endTime'])],
        ...(isset($relationOptions) && ! $bulkEditor ? ['relations' => ['label' => 'Relacionado', 'icon' => 'bi-link-45deg']] : []),
    ]">
    <div class="task-properties-layout">
        <aside class="task-properties-panel lt-cm-form" aria-label="Propiedades de la tarea">
            <x-ui.category-select name="category" :id="$dialogId.'-category'" :options="$categories" wire:model="category" />
            @if (! $bulkEditor && isset($statusOptions))
                <x-ui.select name="statusId" label="Estado" :id="$dialogId.'-status'" :options="$statusOptions"
                    :placeholder="$editingId ? 'Conservar estado actual' : 'Estado inicial (por hacer)'" wire:model="statusId" />
            @endif
            <x-ui.select name="priority" label="Prioridad" :id="$dialogId.'-priority'" :options="$priorities" placeholder="Sin prioridad" wire:model="priority" />
            <x-ui.select name="size" label="Tamaño" :id="$dialogId.'-size'" :options="$sizes" placeholder="Sin tamaño" wire:model="size" />
        </aside>
        <div class="task-properties-content">
            <x-ui.form-dialog-section name="basic" title="Información básica">
                <div class="lt-cm-form">
                    @if ($bulkEditor)
                        <x-ui.textarea name="bulkTitles" label="Tareas" :id="$dialogId.'-bulk-titles'" rows="6" wire:model="bulkTitles"
                            help="Una tarea por línea. Los campos comunes se aplican a todas." />
                    @else
                        <x-ui.field name="title" label="Título" :id="$dialogId.'-task-title'" wire:model="title" required />
                    @endif
                    @include('partials.markdown-editor', [
                        'model' => 'description', 'mode' => 'descriptionMode', 'modeValue' => $descriptionMode,
                        'content' => $description, 'id' => $dialogId.'-description', 'deferred' => $localEditor,
                        'placeholder' => 'Detalles de la tarea. Admite Markdown.',
                    ])
                    @if ($showCompletionAction ?? false)
                        <div><button type="button" wire:click="toggleComplete('{{ $editingId }}')" class="md-btn-text"><i class="bi bi-{{ $completed ? 'arrow-counterclockwise' : 'check2-circle' }}" aria-hidden="true"></i> {{ $completed ? 'Reabrir' : 'Completar' }}</button></div>
                    @endif
                </div>
            </x-ui.form-dialog-section>
            <x-ui.form-dialog-section name="privacy" title="Privacidad">
                <label class="md-checkbox"><input type="checkbox" wire:model="isPrivate"><i class="bi bi-lock" aria-hidden="true"></i> Tarea privada</label>
            </x-ui.form-dialog-section>
            <x-ui.form-dialog-section name="schedule" title="Programación">
                <div class="lt-cm-form">
                    @include('livewire.task.partials.schedule-fields', ['idPrefix' => $dialogId, 'startModel' => 'startDate', 'startTimeModel' => 'startTime', 'endModel' => 'endDate', 'endTimeModel' => 'endTime', 'durationAction' => 'applyDuration'])
                    @if ($showRecurrenceFields ?? false)
                        <div class="d-flex flex-wrap align-items-center gap-3">
                            @if ($localEditor ?? false)
                                <label class="md-checkbox"><input type="checkbox" wire:model="isRecurrent"><i class="bi bi-arrow-repeat"></i> Tarea recurrente</label>
                                <div x-show="$wire.isRecurrent">
                                    <span class="md-chip-tonal" x-show="$wire.nativeRecurrenceRule" x-text="$wire.nativeRecurrenceRule" title="Esta regla CalDAV se conserva sin cambios"></span>
                                    <div class="md-text-field lt-cm-field--sm" x-show="!$wire.nativeRecurrenceRule"><input type="number" min="1" wire:model="recurrenceIntervalDays" placeholder=" " id="{{ $dialogId }}-recurrence-days"><label for="{{ $dialogId }}-recurrence-days">Repetir cada (días)</label></div>
                                </div>
                            @else
                            <label class="md-checkbox"><input type="checkbox" wire:model.live="isRecurrent"><i class="bi bi-arrow-repeat"></i> Tarea recurrente</label>
                            @if ($isRecurrent)
                                @if (($nativeRecurrenceRule ?? '') !== '')
                                    <span class="md-chip-tonal" title="Esta regla CalDAV se conserva sin cambios"><i class="bi bi-calendar2-week"></i> {{ $nativeRecurrenceRule }}</span>
                                @else
                                    <div class="md-text-field lt-cm-field--sm"><input type="number" min="1" wire:model="recurrenceIntervalDays" placeholder=" " id="{{ $dialogId }}-recurrence-days"><label for="{{ $dialogId }}-recurrence-days">Repetir cada (días)</label></div>
                                @endif
                            @endif
                            @endif
                        </div>
                    @endif
                </div>
            </x-ui.form-dialog-section>
            @if (isset($relationOptions) && ! $bulkEditor)
                <x-ui.form-dialog-section name="relations" title="Relacionado">
                    <div class="lt-cm-form">
                        @foreach ($relations as $index => $relation)
                            <div class="row g-2 align-items-center" wire:key="task-relation-{{ $index }}">
                                <div class="col-5">
                                    <x-ui.select :name="'relations.'.$index.'.type'" label="Módulo" :id="$dialogId.'-relation-type-'.$index"
                                        :options="$relationModules" placeholder="Elige un módulo" wire:model.live="relations.{{ $index }}.type" />
                                </div>
                                {{-- Las opciones del combobox se fijan al iniciar Alpine: cambiar de módulo debe recrearlo. --}}
                                <div class="col" wire:key="task-relation-{{ $index }}-{{ $relation['type'] ?? '' }}-picker">
                                    <x-ui.combobox :name="'relations.'.$index.'.id'" label="Relación" :id="$dialogId.'-relation-id-'.$index"
                                        :options="$relationOptions[$relation['type'] ?? ''] ?? []" placeholder="Sin relación"
                                        :empty="($relation['type'] ?? '') === '' ? 'Elige primero un módulo' : 'Sin resultados'"
                                        wire:model="relations.{{ $index }}.id" />
                                </div>
                                <div class="col-auto">
                                    <x-ui.icon-action icon="bi-x-lg" label="Quitar relación" wire:click="removeRelation({{ $index }})" />
                                </div>
                            </div>
                        @endforeach
                        <div><button type="button" wire:click="addRelation" class="md-btn-text"><i class="bi bi-plus-lg" aria-hidden="true"></i> Añadir relación</button></div>
                    </div>
                </x-ui.form-dialog-section>
            @endif
        </div>
    </div>
</x-ui.form-dialog>
