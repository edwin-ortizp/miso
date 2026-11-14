        <section class="md-card-elevated d-grid gap-3" aria-labelledby="game-settings-play">
            <h2 class="md-title-medium mb-0" id="game-settings-play">Juego</h2>
            <x-ui.switch name="game-enabled" label="Juego activo" wire:model="enabled" :checked="$enabled"
                         help="Si lo apagas, conservas tus balls, tu colección y tu experiencia." />
        </section>
        <section class="md-card-elevated d-grid gap-3" aria-labelledby="game-settings-seasons">
            <h2 class="md-title-medium mb-0" id="game-settings-seasons">Temporadas</h2>
            <x-ui.switch name="game-seasons" label="Juego de temporadas" wire:model="seasonsEnabled" :checked="$seasonsEnabled"
                         help="Si lo apagas, el juego usa sus reglas base. No pierdes nada." />
        </section>
        <section class="md-card-elevated d-grid gap-3" aria-labelledby="game-settings-director">
            <h2 class="md-title-medium mb-0" id="game-settings-director">Director</h2>
            <x-ui.switch name="game-director" label="Director activo" wire:model="directorEnabled" :checked="$directorEnabled"
                         help="Permite que el director proponga aventuras y misiones según tu progreso." />
        </section>