<div>
    @if ($formation && $showUpcoming)
        <x-ui.card title="Próximas reservas">
            @forelse ($upcoming as $entry)
                <x-ui.action variant="text" wire:key="{{ $entry['id'] }}"
                    wire:click="edit('{{ $entry['extendedProps']['reservation'] }}', '{{ $entry['extendedProps']['occurrence'] }}')">
                    {{ \Carbon\Carbon::parse($entry['start'])->locale('es')->translatedFormat('j M · H:i') }} · {{ $entry['title'] }}
                </x-ui.action>
            @empty
                <p>No hay reservas en los próximos 28 días.</p>
            @endforelse
            <x-ui.action variant="text" :href="route('tasks.calendar')">Ver calendario</x-ui.action>
        </x-ui.card>
    @endif
    @php
        $sections = collect([
            'general' => ['label' => 'General', 'icon' => 'bi-card-text'],
            'schedule' => ['label' => 'Horario', 'icon' => 'bi-calendar-event'],
            'tasks' => ['label' => 'Tareas vinculadas', 'icon' => 'bi-list-check'],
        ])->map(fn ($section, $key) => $section + ['error' => $errors->hasAny(\App\Livewire\Learning\StudyReservationEditor::SECTION_FIELDS[$key])])->all();
        $taskError = $errors->first('draft.task_ids') ?: $errors->first('draft.task_ids.*');
    @endphp

    <x-ui.form-dialog :open="$open" :title="$reservation ? 'Editar reserva' : 'Reservar tiempo de estudio'"
        close="close" submit-action="save" id="study-reservation-dialog" icon="bi-calendar-plus" :sections="$sections"
        validation-section-model="validationSection" validation-attempt-model="validationAttempt">
        <x-ui.form-dialog-section name="general" title="General">
            <x-ui.field name="draft.title" label="Título" wire:model="draft.title" maxlength="160" required />
            <x-ui.combobox name="draft.formation_id" label="Formación (opcional)" wire:model.live="draft.formation_id"
                :options="$formations" placeholder="Sin formación" empty="No hay formaciones que coincidan." />
            @if (count($units))
                <x-ui.combobox name="draft.unit_id" label="Unidad (opcional)" wire:model="draft.unit_id"
                    :options="$units" placeholder="Sin unidad" empty="No hay unidades que coincidan." />
            @endif
        </x-ui.form-dialog-section>

        <x-ui.form-dialog-section name="schedule" title="Horario">
            @if ($repeats)
                <x-ui.select name="scope" label="Aplicar a"
                    x-data="{ changing: false }" x-bind:disabled="changing" x-bind:value="$wire.scope"
                    x-on:change="
                        const next = $event.target.value;
                        changing = true;
                        try {
                            if (!await $wire.changeScope(next) && window.confirm('¿Descartar los cambios de este alcance?')) {
                                await $wire.changeScope(next, true);
                            }
                        } finally {
                            $el.value = $wire.scope;
                            changing = false;
                        }
                    "
                    :selected="$scope" :options="['one' => 'Esta ocurrencia', 'series' => 'Toda la serie']"
                    :help="$scope === 'series' ? 'Guardar toda la serie restablece las excepciones.' : null" />
            @endif
            <x-ui.field name="draft.date" label="Fecha" type="date" wire:model="draft.date" required />
            <div class="md-field-pair">
                <x-ui.field name="draft.starts_at" label="Hora de inicio" type="time" wire:model="draft.starts_at" required />
                <x-ui.field name="draft.ends_at" label="Hora de finalización" type="time" wire:model="draft.ends_at" required />
            </div>
            @if (!$repeats || $scope === 'series')
                <x-ui.select name="draft.frequency" label="Repetir" wire:model.live="draft.frequency"
                    :options="['none' => 'No repetir', 'daily' => 'Diariamente', 'weekly' => 'Semanalmente']" />
                @if (($draft['frequency'] ?? 'none') !== 'none')
                    <x-ui.field name="draft.interval" :label="$draft['frequency'] === 'daily' ? 'Cada cuántos días' : 'Cada cuántas semanas'"
                        type="number" min="1" max="52" wire:model="draft.interval" required />
                    @if ($draft['frequency'] === 'weekly')
                        <x-ui.choice-chips name="draft.weekdays" label="Días de la semana"
                            :options="[1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo']"
                            :error="$errors->first('draft.weekdays') ?: $errors->first('draft.weekdays.*')" />
                    @endif
                    <x-ui.field name="draft.until" label="Fecha límite de repetición (opcional)" type="date" wire:model="draft.until" />
                @endif
            @endif
            @if ($reservation)
                <div>
                    <x-ui.action variant="text" wire:click="cancelReservation"
                        wire:confirm="¿Cancelar la reserva según el alcance seleccionado?">Cancelar reserva</x-ui.action>
                </div>
            @endif
        </x-ui.form-dialog-section>

        <x-ui.form-dialog-section name="tasks" title="Tareas vinculadas">
            <x-ui.combobox name="draft.task_ids" label="Buscar tareas pendientes" multiple
                wire:key="reservation-tasks-{{ $scope }}" wire:model="draft.task_ids" search-model="taskSearch"
                :options="$taskOptions" :selected-options="$selectedTasks" :search-term="$taskTerm" :more-results="$moreTasks"
                :error="$taskError" empty="No hay tareas pendientes que coincidan."
                help="Busca por nombre y selecciona las tareas que quieras vincular." />
        </x-ui.form-dialog-section>
    </x-ui.form-dialog>
</div>
