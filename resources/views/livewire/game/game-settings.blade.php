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

        <div><button type="submit" class="md-btn-filled"><i class="bi bi-save"></i> Guardar ajustes</button></div>
    </form>
</x-module-shell>
