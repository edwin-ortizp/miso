<div>
    <x-ui.form-dialog :open="$kind !== ''" close="close" submit-action="save" id="panel-quick-dialog"
        :title="match($kind) { 'water' => 'Registrar bebida', 'mood' => 'Registrar ánimo y energía', 'journal' => 'Diario del día', 'avoid' => 'Registrar recaída', 'snooze' => 'Posponer tarea', default => 'Registro' }">
        <div class="d-flex flex-column gap-3">
            <p class="md-body-small">{{ $selectedDate }}</p>
            @if ($kind === 'water')
                <x-ui.select name="drinkId" label="Bebida" :options="$drinks" :selected="$drinkId" wire:model="drinkId" :required="true" />
                <x-ui.field name="amount" label="Cantidad (ml)" type="number" min="1" max="10000" wire:model="amount" :required="true" />
            @elseif ($kind === 'mood')
                <x-ui.select name="moodId" label="Ánimo" :options="$moods" :selected="$moodId" wire:model="moodId" :required="true" />
                <x-ui.select name="energy" label="Energía" :options="[1 => '1 · Muy baja', 2 => '2 · Baja', 3 => '3 · Media', 4 => '4 · Alta', 5 => '5 · Muy alta']" :selected="$energy" wire:model="energy" placeholder="Sin registrar" />
            @elseif ($kind === 'snooze')
                <x-ui.field name="scheduledDate" label="Fecha" type="date" wire:model="scheduledDate" :required="true" />
                <x-ui.field name="scheduledTime" label="Hora" type="time" wire:model="scheduledTime" help="Déjala vacía para una tarea sin hora." />
            @else
                <x-ui.textarea name="text" :label="$kind === 'journal' ? '¿Qué quieres recordar de hoy?' : 'Nota (opcional)'" rows="6" wire:model="text" :required="$kind === 'journal'" />
                @if ($kind === 'journal')
                    <x-ui.field name="summary" label="Resumen" maxlength="255" wire:model="summary" />
                @else
                    <p class="md-body-medium">Registrar una recaída conserva tu historial y permite observar tus patrones.</p>
                @endif
            @endif
        </div>
    </x-ui.form-dialog>
</div>
