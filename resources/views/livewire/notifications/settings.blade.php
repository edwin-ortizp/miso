<form wire:submit="save" class="settings-notifications">
    <x-ui.card class="settings-preference-card" icon="bi-bell">
        <x-slot:header><h2 class="md-title-medium mb-0">Avisos automáticos</h2></x-slot:header>
        <p class="settings-form-intro">Elige los avisos automáticos que quieres recibir. Los recordatorios se configuran en cada elemento.</p>
        <div class="settings-preference-list">
            @foreach (['birthdays' => 'Cumpleaños de hoy', 'tasks' => 'Tareas que vencen mañana', 'plans' => 'Planes para mañana'] as $key => $label)
                <x-ui.switch :name="'preferences.'.$key" :label="$label" wire:model="preferences.{{ $key }}" />
            @endforeach
        </div>
    </x-ui.card>

    <x-ui.card class="settings-preference-card" icon="bi-phone-vibrate">
        <x-slot:header><h2 class="md-title-medium mb-0">Este dispositivo</h2></x-slot:header>
        <div class="settings-device" x-data="ltPushToggle(@js($vapidKey))" @android-push-state.window="if (android) state = $event.detail.state">
            <p class="settings-form-intro">Tu guardián te escribe si hay algo pendiente. Máximo {{ config('game.nudges.daily_cap') }} avisos al día.</p>
            <p x-show="state === 'unsupported'" x-cloak role="status" x-text="android ? 'El servicio de avisos Android no está disponible. Revisa Google Play Services e inténtalo de nuevo.' : 'Este navegador no recibe avisos. En iPhone, instala MiSo en tu pantalla de inicio y ábrela desde ahí.'"></p>
            <p x-show="state === 'denied'" x-cloak role="status" x-text="android ? 'Los avisos no tienen permiso. Puedes activarlos en los ajustes de Android.' : 'Bloqueaste los avisos para MiSo. Actívalos en los permisos del navegador y vuelve aquí.'"></p>
            <p x-show="state === 'blocked'" x-cloak role="status">El canal de avisos está bloqueado en los ajustes de Android.</p>
            <p x-show="state === 'error'" x-cloak role="status">No pudimos conectar los avisos con tu cuenta. Vuelve a intentarlo.</p>
            <x-ui.action type="button" variant="text" x-show="android && (state === 'denied' || state === 'blocked')" x-cloak x-on:click="openSettings()">Abrir ajustes de Android</x-ui.action>
            <x-ui.action type="button" variant="text" x-show="state === 'error'" x-cloak x-on:click="init()">Reintentar</x-ui.action>
            <p x-show="state === 'unconfigured'" x-cloak role="status" x-text="android ? 'Los avisos Android aún no están configurados.' : 'Los avisos aún no están configurados en el servidor.'"></p>
            <p x-show="state === 'off'" x-cloak role="status">Los avisos están desactivados en este dispositivo.</p>
            <p x-show="state === 'on'" x-cloak role="status">Los avisos están activados en este dispositivo.</p>
            <div x-show="state === 'off' || state === 'on'" x-cloak>
                <x-ui.action type="button" x-show="state === 'off'" x-on:click="enable()" x-bind:disabled="busy">Activar en este dispositivo</x-ui.action>
                <x-ui.action type="button" variant="text" x-show="state === 'on'" x-on:click="disable()" x-bind:disabled="busy">Desactivar en este dispositivo</x-ui.action>
            </div>
        </div>
    </x-ui.card>

    @if ($devices > 0)
        <x-ui.card class="settings-preference-card" icon="bi-sliders">
            <x-slot:header><h2 class="md-title-medium mb-0">Preferencias de avisos al celular</h2></x-slot:header>
            <p class="settings-form-intro">Se aplican a tus dispositivos registrados. La activación se gestiona en cada navegador.</p>
            <fieldset class="settings-preference-group">
                <legend class="md-label-large">Cuándo te escribe tu guardián</legend>
                <div class="settings-preference-list">
                    @foreach ($nudgeSlots as $key => $nudgeWindow)
                        <x-ui.switch :name="'preferences.nudge_'.$key" :label="$nudgeWindow['label'].' '.$nudgeWindow['from'].'–'.$nudgeWindow['to']" wire:model="preferences.nudge_{{ $key }}" />
                    @endforeach
                </div>
            </fieldset>
            <fieldset class="settings-preference-group">
                <legend class="md-label-large">También al celular</legend>
                <div class="settings-preference-list">
                    @foreach (['push_birthday' => 'Cumpleaños de hoy', 'push_task_due' => 'Tareas que vencen mañana', 'push_plan_due' => 'Planes para mañana', 'push_reminder' => 'Recordatorios', 'push_weekly_insights' => 'Resumen del domingo'] as $key => $label)
                        <x-ui.switch :name="'preferences.'.$key" :label="$label" wire:model="preferences.{{ $key }}" />
                    @endforeach
                </div>
            </fieldset>
        </x-ui.card>
    @endif

    <div class="settings-preferences-actions">
        <x-ui.action type="submit" icon="bi-check-lg" wire:loading.attr="disabled" wire:target="save">Guardar preferencias</x-ui.action>
        @if ($message)<p role="status" aria-live="polite">{{ $message }}</p>@endif
    </div>
</form>
