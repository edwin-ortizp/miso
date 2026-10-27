<div class="lt-status__group" x-data="pomodoroTimer({
    workDuration: {{ $workDuration }}, shortBreak: {{ $shortBreak }}, longBreak: {{ $longBreak }},
    storageKey: 'life-tracker:pomodoro:{{ auth()->id() }}',
    saveSession: (start, end, description, token) => $wire.saveSession(start, end, description, token)
})" @status-focus-start.window="if (!isRunning && !pendingCompletion && !startedAt) { setMode('work'); start(); } else if (!isRunning) { toggle(); }">
    <a href="{{ route('pomodoro') }}" wire:navigate @class(['lt-status__item', 'is-warning' => $goalReached])
       title="{{ $goalReached ? 'Meta de foco cumplida: considera descansar' : 'Foco registrado hoy frente a la meta' }}">
        <i class="bi {{ $goalReached ? 'bi-cup-hot' : 'bi-hourglass-split' }}" aria-hidden="true"></i>
        <span>Foco hoy: {{ $todayLabel }} / {{ $goalLabel }}</span>
    </a>

    <div class="lt-status__timer">
        <x-ui.action variant="text" size="sm" class="lt-status__item" @click="toggle()" x-bind:disabled="isCompleting"
                     x-bind:class="{ 'is-active': isRunning, 'is-break': mode !== 'work' }"
                     x-bind:title="pendingCompletion ? 'Reintentar guardado' : (isRunning ? 'Pausar' : 'Iniciar foco')">
            <i class="bi" x-bind:class="isRunning ? 'bi-pause-circle' : 'bi-play-circle'" aria-hidden="true"></i>
            <span x-text="(mode === 'work' ? '' : 'Pausa · ') + displayTime" role="timer" aria-live="off"></span>
        </x-ui.action>
        <x-ui.menu label="Opciones del temporizador" icon="bi-chevron-up" size="sm" class="lt-status__menu">
            <x-ui.menu-item icon="bi-check2-circle" @click="finishEarly()" x-bind:disabled="isCompleting || mode !== 'work'">Finalizar y guardar</x-ui.menu-item>
            <x-ui.menu-item icon="bi-arrow-counterclockwise" @click="reset()" x-bind:disabled="isCompleting || pendingCompletion">Reiniciar</x-ui.menu-item>
            <x-ui.menu-divider />
            <x-ui.menu-item icon="bi-briefcase" @click="setMode('work')">Trabajo</x-ui.menu-item>
            <x-ui.menu-item icon="bi-cup" @click="setMode('shortBreak')">Pausa corta</x-ui.menu-item>
            <x-ui.menu-item icon="bi-cup-hot" @click="setMode('longBreak')">Pausa larga</x-ui.menu-item>
        </x-ui.menu>
        <span class="lt-status__error" x-show="errorMessage" x-text="errorMessage" role="alert"></span>
    </div>
</div>
