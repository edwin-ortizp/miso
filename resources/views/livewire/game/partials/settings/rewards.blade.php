<section class="md-card-elevated d-grid gap-3" aria-labelledby="game-settings-sources"><h2 id="game-settings-sources" class="md-title-medium mb-0">Tus recompensas</h2>
            <x-ui.switch name="game-tasks" label="Dar balls por tareas completadas" wire:model="tasks" :checked="$tasks" />
            <x-ui.switch name="game-habits" label="Dar balls por hábitos" wire:model="habits" :checked="$habits" />
            <x-ui.switch name="game-focus" label="Dar balls por foco" wire:model="focus" :checked="$focus"
                         help="Una ball al cumplir tu meta diaria de minutos, no por cada pomodoro." />
            <x-ui.switch name="game-goals" label="Dar balls por metas" wire:model="goals" :checked="$goals" />
            <x-ui.switch name="game-health" label="Dar balls por salud" wire:model="health" :checked="$health"
                         help="Al cumplir tu meta diaria de agua o de ejercicio." />
            <x-ui.field name="dailyLimit" label="Máximo de balls por día" type="number" wire:model="dailyLimit" :value="$dailyLimit"
                        :min="config('game.limits.daily_limit')[0]" :max="config('game.limits.daily_limit')[1]" />

</section>
        <section class="md-card-elevated d-grid gap-3" aria-labelledby="game-settings-consistency">
            <h2 class="md-title-medium mb-0" id="game-settings-consistency">Constancia</h2>
            <p class="md-body-small game-muted mb-0">Tu equipo gana +1 xp la primera vez que registras algo de cada tipo en el día.</p>
            @php($consistencyLabels = ['vehicle' => 'Vehículo (gasolina, gastos, mantenimiento)', 'wellbeing' => 'Bienestar (diario, ánimo, energía)', 'people' => 'Personas (relaciones, visitas a planes)', 'home' => 'Hogar (comidas y compras)', 'personal_tasks' => 'Tareas personales (categoría Personal)'])
            @foreach ($consistencyLabels as $key => $label)
                <x-ui.switch name="game-consistency-{{ $key }}" :label="$label" wire:model="consistency.{{ $key }}" :checked="$consistency[$key] ?? true" />
            @endforeach
        </section>
        <section class="md-card-elevated d-grid gap-3" aria-labelledby="game-settings-streak" id="racha">
            <h2 class="md-title-medium mb-0" id="game-settings-streak">Racha</h2>
            <x-ui.switch name="game-streak-visible" label="Mostrar mi racha" wire:model="streakVisible" :checked="$streakVisible"
                         help="Si la ocultas, tus días siguen guardándose y puedes volver a verla cuando quieras." />
            <x-ui.field name="streakThreshold" label="Acciones para cumplir el día" type="number" wire:model="streakThreshold" :value="$streakThreshold"
                        :min="config('game.limits.streak_threshold')[0]" :max="config('game.limits.streak_threshold')[1]"
                        help="Entre 1 y 10, en al menos dos áreas distintas (tareas, hábitos, salud…)." />
        </section>
        <section class="md-card-elevated d-grid gap-3" aria-labelledby="game-settings-challenge" id="reto">
            <h2 class="md-title-medium mb-0" id="game-settings-challenge">Reto del día</h2>
            <x-ui.switch name="game-daily-challenge" label="Reto del día" wire:model="dailyChallenge" :checked="$dailyChallenge"
                         help="Un reto pequeño cada día, con una ball al cumplirlo." />
        </section>