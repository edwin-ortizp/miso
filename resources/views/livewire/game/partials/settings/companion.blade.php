        <section class="md-card-elevated d-grid gap-3" aria-labelledby="game-settings-companion-chat" id="companero">
            <h2 class="md-title-medium mb-0" id="game-settings-companion-chat">Tu compañero al crear</h2>
            <fieldset class="d-grid gap-2">
                <legend class="md-label-large">Presentación de creación</legend>
                <label class="game-settings-choice"><input type="radio" name="creation-style" value="pokemon" wire:model.live="creationStyle"> Con mi Pokémon</label>
                <label class="game-settings-choice"><input type="radio" name="creation-style" value="traditional" wire:model.live="creationStyle"> Tradicional</label>
            </fieldset>
            @if ($companions->isEmpty())
                <p class="md-body-medium game-muted mb-0">Aún no tienes compañeros: verás el botón tradicional hasta que conozcas al primero.</p>
            @else
                <div class="d-grid gap-1">
                    <x-ui.select name="favoriteCompanionId" id="game-favorite-companion" label="Pokémon favorito"
                                 placeholder="El compañero del día" :selected="$favoriteCompanionId"
                                 :options="$companions->mapWithKeys(fn ($c) => [$c->id => $c->species->name.' · Nv. '.$c->level])->all()"
                                 wire:model="favoriteCompanionId" help="Aparece en lugar del botón de crear y te ayuda a registrar rápido." />
                </div>
            @endif
        </section>