<x-module-shell module="game" archetype="settings">
    @if ($message)
        <div class="md-card-filled mb-3 py-3" role="status" aria-live="polite">{{ $message }}</div>
    @endif

    <form wire:submit="save" class="d-grid gap-3">
        <section class="md-card-elevated d-grid gap-3" aria-labelledby="game-settings-play">
            <h2 class="md-title-medium mb-0" id="game-settings-play">Juego</h2>
            <x-ui.switch name="game-enabled" label="Juego activo" wire:model="enabled" :checked="$enabled"
                         help="Si lo apagas, conservas tus balls, tu colección y tu experiencia." />
            <x-ui.switch name="game-tasks" label="Dar balls por tareas completadas" wire:model="tasks" :checked="$tasks" />
            <x-ui.switch name="game-habits" label="Dar balls por hábitos" wire:model="habits" :checked="$habits" />
            <x-ui.switch name="game-focus" label="Dar balls por foco" wire:model="focus" :checked="$focus"
                         help="Una ball al cumplir tu meta diaria de minutos, no por cada pomodoro." />
            <x-ui.switch name="game-goals" label="Dar balls por metas" wire:model="goals" :checked="$goals" />
            <x-ui.switch name="game-health" label="Dar balls por salud" wire:model="health" :checked="$health"
                         help="Al cumplir tu meta diaria de agua o de ejercicio." />
            <div>
                <div class="md-text-field">
                    <input type="number" wire:model="dailyLimit" id="game-daily-limit" placeholder=" "
                           min="{{ config('game.limits.daily_limit')[0] }}" max="{{ config('game.limits.daily_limit')[1] }}">
                    <label for="game-daily-limit">Máximo de balls por día</label>
                </div>
                @error('dailyLimit') <small class="game-error">{{ $message }}</small> @enderror
            </div>
        </section>

        <section class="md-card-elevated d-grid gap-3" aria-labelledby="game-settings-consistency">
            <h2 class="md-title-medium mb-0" id="game-settings-consistency">Constancia</h2>
            <p class="md-body-small game-muted mb-0">Tu equipo gana +1 xp la primera vez que registras algo de cada tipo en el día.</p>
            @php($consistencyLabels = ['vehicle' => 'Vehículo (gasolina, gastos, mantenimiento)', 'wellbeing' => 'Bienestar (diario, ánimo, energía)', 'people' => 'Personas (relaciones, visitas a planes)', 'home' => 'Hogar (comidas y compras)', 'personal_tasks' => 'Tareas personales (categoría Personal)'])
            @foreach ($consistencyLabels as $key => $label)
                <x-ui.switch name="game-consistency-{{ $key }}" :label="$label" wire:model="consistency.{{ $key }}" :checked="$consistency[$key] ?? true" />
            @endforeach
        </section>

        <section class="md-card-elevated d-grid gap-3" aria-labelledby="game-settings-director">
            <h2 class="md-title-medium mb-0" id="game-settings-director">Director</h2>
            <x-ui.switch name="game-director" label="Director activo" wire:model="directorEnabled" :checked="$directorEnabled"
                         help="Propone temporadas desde tu schedule de Paseo." />
        </section>

        <section class="md-card-elevated d-grid gap-3" aria-labelledby="game-settings-seasons">
            <h2 class="md-title-medium mb-0" id="game-settings-seasons">Temporadas</h2>
            <x-ui.switch name="game-seasons" label="Juego de temporadas" wire:model="seasonsEnabled" :checked="$seasonsEnabled"
                         help="Si lo apagas, el juego usa sus reglas base. No pierdes nada." />
        </section>

        <section class="md-card-elevated d-grid gap-3" aria-labelledby="game-settings-celebrations">
            <h2 class="md-title-medium mb-0" id="game-settings-celebrations">Celebraciones</h2>
            <x-ui.switch name="game-celebrations" label="Mostrar celebraciones" wire:model="celebrations" :checked="$celebrations"
                         help="Una animación al azar cuando ganas una ball, subes de nivel, evolucionas o cumples una misión o meta." />
            <div class="game-sound-setting">
                <x-ui.switch name="game-sounds" label="Sonidos" wire:model="sounds" :checked="$sounds"
                             help="Suaves y cortos, al ganar o abrir balls." />
                <x-ui.action variant="text" icon="bi-volume-up" @click="window.playGameSound?.('ball', { force: true })">Probar sonido</x-ui.action>
            </div>
            <fieldset class="game-celebration-options">
                <legend class="md-label-large">Cuáles pueden salir</legend>
                @foreach (config('game.celebration_catalog') as $key => $label)
                    <x-ui.switch name="game-celebration-{{ $key }}" :label="$label" wire:model="celebrationSet" value="{{ $key }}"
                                 :checked="in_array($key, $celebrationSet, true)" />
                @endforeach
            </fieldset>
        </section>

        <div><button type="submit" class="md-btn-filled"><i class="bi bi-save"></i> Guardar ajustes</button></div>
    </form>
</x-module-shell>
