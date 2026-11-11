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
        </x-ui.card>
    </x-ui.section>

    {{-- CHG-068: avisos push del guardián. El permiso del navegador se pide solo al activar. --}}
    <x-ui.section title="Avisos de tu guardián" :level="2">
        <x-ui.card icon="bi-phone-vibrate">
            <div x-data="ltPushToggle(@js($vapidKey))">
                <p>Te escribe solo si hay algo pendiente. Máximo {{ config('game.nudges.daily_cap') }} avisos al día.</p>

                <p x-show="state === 'unsupported'" x-cloak role="status">Este navegador no recibe avisos. En iPhone, instala MiSo en tu pantalla de inicio y ábrela desde ahí.</p>
                <p x-show="state === 'denied'" x-cloak role="status">Bloqueaste los avisos para MiSo. Actívalos en los permisos del navegador y vuelve aquí.</p>
                <p x-show="state === 'unconfigured'" x-cloak role="status">Los avisos aún no están configurados en el servidor.</p>

                <div x-show="state === 'off' || state === 'on'" x-cloak>
                    <x-ui.action type="button" x-show="state === 'off'" x-on:click="enable()" x-bind:disabled="busy">Activar en este dispositivo</x-ui.action>
                    <x-ui.action type="button" variant="text" x-show="state === 'on'" x-on:click="disable()" x-bind:disabled="busy">Desactivar en este dispositivo</x-ui.action>
                </div>

                @if ($devices > 0)
                    <form wire:submit="save" class="mt-3">
                        <p class="md-label-large mb-1">Cuándo</p>
                        @foreach ($slots as $key => $slot)
                            <x-ui.switch :name="'preferences.nudge_'.$key" :label="$slot['label'].' '.$slot['from'].'–'.$slot['to']" wire:model="preferences.nudge_{{ $key }}" />
                        @endforeach
                        <p class="md-label-large mb-1 mt-2">También al celular</p>
                        @foreach (['push_birthday' => 'Cumpleaños de hoy', 'push_task_due' => 'Tareas que vencen mañana', 'push_plan_due' => 'Planes para mañana', 'push_reminder' => 'Recordatorios', 'push_weekly_insights' => 'Resumen del domingo'] as $key => $label)
                            <x-ui.switch :name="'preferences.'.$key" :label="$label" wire:model="preferences.{{ $key }}" />
                        @endforeach
                        <x-ui.action type="submit">Guardar</x-ui.action>
                    </form>
                @endif
            </div>
            @if ($message)<p role="status" aria-live="polite">{{ $message }}</p>@endif
        </x-ui.card>
    </x-ui.section>
</div>
