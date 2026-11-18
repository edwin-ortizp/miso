<div>
    @if ($formation)
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
    <x-ui.form-dialog :open="$open" :title="$reservation ? 'Editar reserva' : 'Reservar tiempo de estudio'"
        close="close" submit-action="save" id="study-reservation-dialog" icon="bi-calendar-plus">
        @if ($repeats)
            <x-ui.select name="scope" label="Aplicar a" wire:model.live="scope"
                :options="['one' => 'Esta ocurrencia', 'series' => 'Toda la serie']" />
            @if ($scope === 'series')
                <p>Guardar toda la serie restablece las excepciones.</p>
            @endif
        @endif
        <x-ui.field name="draft.title" label="Título" wire:model="draft.title" maxlength="160" required />
        <x-ui.field name="draft.date" label="Fecha" type="date" wire:model="draft.date" required />
        <x-ui.field name="draft.starts_at" label="Desde" type="time" wire:model="draft.starts_at" required />
        <x-ui.field name="draft.ends_at" label="Hasta" type="time" wire:model="draft.ends_at" required />
        <x-ui.select name="draft.formation_id" label="Formación (opcional)" wire:model.live="draft.formation_id"
            :options="['' => 'Sin formación'] + collect($formations)->all()" />
        @if (count($units))
            <x-ui.select name="draft.unit_id" label="Unidad (opcional)" wire:model="draft.unit_id"
                :options="['' => 'Sin unidad'] + collect($units)->all()" />
        @endif
        @if (!$repeats || $scope === 'series')
            <x-ui.select name="draft.frequency" label="Repetir" wire:model.live="draft.frequency"
                :options="['none' => 'No repetir', 'daily' => 'Diariamente', 'weekly' => 'Semanalmente']" />
            @if (($draft['frequency'] ?? 'none') !== 'none')
                <x-ui.field name="draft.interval" :label="$draft['frequency'] === 'daily' ? 'Cada cuántos días' : 'Cada cuántas semanas'"
                    type="number" min="1" max="52" wire:model="draft.interval" />
                @if ($draft['frequency'] === 'weekly')
                    <fieldset>
                        <legend>Días de la semana</legend>
                        @foreach (['Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sá', 'Do'] as $index => $day)
                            <label class="md-checkbox"><input type="checkbox" wire:model="draft.weekdays" value="{{ $index + 1 }}"> {{ $day }}</label>
                        @endforeach
                    </fieldset>
                @endif
                <x-ui.field name="draft.until" label="Hasta el día (opcional)" type="date" wire:model="draft.until" />
            @endif
        @endif
        @if (count($tasks))
            <fieldset>
                <legend>Tareas vinculadas (opcional)</legend>
                @foreach ($tasks as $id => $title)
                    <label class="md-checkbox" wire:key="reservation-task-{{ $id }}"><input type="checkbox" wire:model="draft.task_ids" value="{{ $id }}"> {{ $title }}</label>
                @endforeach
            </fieldset>
        @endif
        @if ($errors->any())
            <p role="alert">{{ $errors->first() }}</p>
        @endif
        @if ($reservation)
            <button type="button" class="md-btn-text" wire:click="cancelReservation"
                wire:confirm="¿Cancelar la reserva según el alcance seleccionado?">Cancelar reserva</button>
        @endif
    </x-ui.form-dialog>
</div>
