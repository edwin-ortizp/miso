<div>
    <p class="md-body-small">Avisar antes de la fecha del elemento. Sin hora, se toman las 9:00 a. m. de Bogotá.</p>
    @error('reminders')<p role="alert">{{ $message }}</p>@enderror
    @foreach ($reminders as $index => $reminder)
        <div class="md-form-dialog__grid" wire:key="reminder-row-{{ $reminder['id'] ?? 'new-'.$index }}">
            <x-ui.field :name="'reminders.'.$index.'.amount'" :label="'Anticipación '.($index + 1)" type="number" min="0" step="1" wire:model="reminders.{{ $index }}.amount" />
            <x-ui.select :name="'reminders.'.$index.'.unit'" :label="'Unidad '.($index + 1)" :options="\App\Services\Notifications\ManageReminders::UNITS" wire:model="reminders.{{ $index }}.unit" />
            <x-ui.action variant="text" wire:click="removeReminder({{ $index }})" :aria-label="'Quitar recordatorio '.($index + 1)">Quitar</x-ui.action>
        </div>
    @endforeach
    <x-ui.action variant="text" icon="bi-plus" wire:click="addReminder">Añadir recordatorio</x-ui.action>
</div>
