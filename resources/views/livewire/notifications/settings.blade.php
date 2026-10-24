<div>
    <x-ui.section title="Avisos automáticos" :level="2">
        <x-ui.card icon="bi-bell">
            <p>Elige los avisos automáticos que quieres recibir. Los recordatorios se configuran en cada elemento.</p>
            <form wire:submit="save">
                @foreach (['birthdays' => 'Cumpleaños de hoy', 'tasks' => 'Tareas que vencen mañana', 'plans' => 'Planes para mañana'] as $key => $label)
                    <x-ui.switch :name="'preferences.'.$key" :label="$label" wire:model="preferences.{{ $key }}" />
                @endforeach
                <x-ui.action type="submit">Guardar</x-ui.action>
            </form>
            @if ($message)<p role="status">{{ $message }}</p>@endif
        </x-ui.card>
    </x-ui.section>
</div>
