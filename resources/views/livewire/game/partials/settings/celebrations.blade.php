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