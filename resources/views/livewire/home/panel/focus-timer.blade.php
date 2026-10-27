<div x-data="{ focusOpen: false }">
    <x-ui.form-dialog module="pomodoro" :open="$showForm" close="closeForm" submit-action="saveManualSession" id="panel-focus-manual-dialog"
                      title="Registrar sesión de enfoque" icon="bi-stopwatch">
        <div class="d-flex flex-column gap-3">
            <p class="md-body-small mb-0">Registra el tiempo trabajado hoy. La duración se calcula automáticamente.</p>
            <div class="md-field-pair">
                <x-ui.field name="manualStart" label="Inicio" type="time" wire:model.live="manualStart" />
                <x-ui.field name="manualEnd" label="Fin" type="time" wire:model.live="manualEnd" />
            </div>
            <x-ui.field name="manualDescription" label="Descripción (opcional)" wire:model="manualDescription" />
            @if ($manualDurationSeconds !== null)
                <p class="md-body-medium mb-0" role="status">Duración calculada: <strong>{{ intdiv($manualDurationSeconds, 3600) }} h {{ intdiv($manualDurationSeconds % 3600, 60) }} min</strong></p>
            @endif
        </div>
    </x-ui.form-dialog>
    <div x-data="pomodoroTimer({
        workDuration: {{ $workDuration }}, shortBreak: {{ $shortBreak }}, longBreak: {{ $longBreak }},
        storageKey: 'life-tracker:pomodoro:{{ auth()->id() }}',
        loadTask: (id) => $wire.focusTask(id),
        saveSession: (start, end, description, token) => $wire.saveSession(start, end, description, token)
    })" @panel-focus.window="focusOpen = true; openFocus($event.detail)">
        <x-ui.dialog standard expanded state="focusOpen" title="Foco de hoy" size="fullscreen" icon="bi-stopwatch" class="wall-focus-dialog">
            <div class="wall-focus-layout" :class="{ 'has-task': taskId }">
                <div class="wall-focus">
                    <strong x-text="displayTime" role="timer" aria-live="off"></strong>
                    <p>Tiempo de enfoque</p>
                    <p>{{ $workDuration }} min de trabajo · {{ $shortBreak }} min de pausa corta</p>
                    <div class="md-chip-rail" role="group" aria-label="Modo del temporizador">
                        <x-ui.chip variant="filter" x-bind:class="{ selected: mode === 'work' }" x-bind:aria-pressed="mode === 'work'" @click="setMode('work')">Trabajo</x-ui.chip>
                        <x-ui.chip variant="filter" x-bind:class="{ selected: mode === 'shortBreak' }" x-bind:aria-pressed="mode === 'shortBreak'" @click="setMode('shortBreak')">Pausa corta</x-ui.chip>
                        <x-ui.chip variant="filter" x-bind:class="{ selected: mode === 'longBreak' }" x-bind:aria-pressed="mode === 'longBreak'" @click="setMode('longBreak')">Pausa larga</x-ui.chip>
                    </div>
                    <x-ui.field name="focus-description" label="Descripción del registro de foco" x-model="description" />
                    <div class="d-flex flex-wrap gap-2 justify-content-center">
                        <x-ui.action class="wall-control" @click="toggle()" x-bind:disabled="isCompleting"><span x-text="pendingCompletion ? 'Reintentar guardado' : (isRunning ? 'Pausar' : 'Iniciar')"></span></x-ui.action>
                        <x-ui.action class="wall-control" variant="outlined" @click="finishEarly()" x-bind:disabled="isCompleting || mode !== 'work'">Finalizar y guardar</x-ui.action>
                        <x-ui.action class="wall-control" variant="text" @click="reset()" x-bind:disabled="isCompleting || pendingCompletion">Reiniciar</x-ui.action>
                    </div>
                    <p x-show="errorMessage" x-text="errorMessage" role="alert"></p>
                </div>
            <section class="wall-focus-task" x-show="taskId" aria-label="Tarea en foco" x-cloak>
                <p x-show="taskLoading" role="status">Cargando tarea…</p>
                <p x-show="taskError" x-text="taskError" role="alert"></p>
                <template x-if="taskContext">
                    <div class="wall-focus-task__content">
                        <h3 x-text="taskContext.title"></h3>
                        <div class="wall-focus-task__description" tabindex="0" role="region" aria-label="Descripción de la tarea">
                            <div class="md-markdown-preview" x-html="taskContext.html" x-show="taskContext.hasDescription"></div>
                            <p x-show="!taskContext.hasDescription">Esta tarea no tiene descripción</p>
                        </div>
                    </div>
                </template>
                <p x-show="!taskLoading && !taskError && !taskContext">La tarea ya no está disponible.</p>
            </section>
            </div>
            <x-slot:actions><x-ui.action class="wall-control" variant="text" @click="focusOpen = false">Volver al panel</x-ui.action></x-slot:actions>
        </x-ui.dialog>
    </div>
</div>
