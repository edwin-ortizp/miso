<div x-data="{ focusOpen: false }">
    <div x-data="pomodoroTimer({
        workDuration: {{ $workDuration }}, shortBreak: {{ $shortBreak }}, longBreak: {{ $longBreak }},
        storageKey: 'life-tracker:pomodoro:{{ auth()->id() }}',
        saveSession: (start, end, description, token) => $wire.saveSession(start, end, description, token)
    })" @panel-focus.window="focusOpen = true; if ($event.detail.start && !isRunning && !pendingCompletion && !startedAt) { description = $event.detail.description || ''; setMode('work'); start(); }">
        <x-ui.dialog standard expanded state="focusOpen" title="Foco de hoy" size="fullscreen" icon="bi-stopwatch">
            <div class="wall-focus">
                <strong x-text="displayTime" role="timer" aria-live="off"></strong>
                <p x-text="description || 'Tiempo de enfoque'"></p>
                <p>{{ $workDuration }} min de trabajo · {{ $shortBreak }} min de pausa corta</p>
                <div class="md-chip-rail" role="group" aria-label="Modo del temporizador">
                    <x-ui.chip variant="filter" x-bind:class="{ selected: mode === 'work' }" x-bind:aria-pressed="mode === 'work'" @click="setMode('work')">Trabajo</x-ui.chip>
                    <x-ui.chip variant="filter" x-bind:class="{ selected: mode === 'shortBreak' }" x-bind:aria-pressed="mode === 'shortBreak'" @click="setMode('shortBreak')">Pausa corta</x-ui.chip>
                    <x-ui.chip variant="filter" x-bind:class="{ selected: mode === 'longBreak' }" x-bind:aria-pressed="mode === 'longBreak'" @click="setMode('longBreak')">Pausa larga</x-ui.chip>
                </div>
                <x-ui.field name="focus-description" label="Descripción" x-model="description" />
                <div class="d-flex flex-wrap gap-2 justify-content-center">
                    <x-ui.action class="wall-control" @click="toggle()" x-bind:disabled="isCompleting"><span x-text="pendingCompletion ? 'Reintentar guardado' : (isRunning ? 'Pausar' : 'Iniciar')"></span></x-ui.action>
                    <x-ui.action class="wall-control" variant="outlined" @click="finishEarly()" x-bind:disabled="isCompleting || mode !== 'work'">Finalizar y guardar</x-ui.action>
                    <x-ui.action class="wall-control" variant="text" @click="reset()" x-bind:disabled="isCompleting || pendingCompletion">Reiniciar</x-ui.action>
                </div>
                <p x-show="errorMessage" x-text="errorMessage" role="alert"></p>
            </div>
            <x-slot:actions><x-ui.action class="wall-control" variant="text" @click="focusOpen = false">Volver al panel</x-ui.action></x-slot:actions>
        </x-ui.dialog>
    </div>
</div>
