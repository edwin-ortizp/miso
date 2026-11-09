<x-module-shell module="game">
    @if ($message)
        <div class="md-card-filled mb-3 py-3" role="status" aria-live="polite">{{ $message }}</div>
    @endif

    <section class="md-card-elevated">
        @if ($active)
            <p class="md-label-large game-muted mb-1">{{ $active->starts_at->translatedFormat('j M') }} – {{ $active->ends_at->translatedFormat('j M Y') }}</p>
            <h2 class="md-title-large mb-2">{{ $active->name }}</h2>
            <p class="md-body-large">{{ $active->narrative }}</p>
            @if ($activeRules)
                <ul class="game-season__rules">
                    @foreach ($activeRules as $rule)
                        <li class="md-body-medium">{{ $rule }}</li>
                    @endforeach
                </ul>
            @endif
            <div class="d-flex justify-content-end">
                <button type="button" class="md-btn-outlined" wire:click="askRevert({{ $active->id }})">Revertir temporada</button>
            </div>
        @else
            <x-ui.state variant="empty" icon="bi-calendar-event" title="Aún no hay temporada. El juego usa sus reglas base." />
        @endif

        @if ($scheduled)
            <p class="md-body-medium mt-3 mb-0">
                Siguiente: <strong>{{ $scheduled->name }}</strong> desde el {{ $scheduled->starts_at->translatedFormat('j M') }}.
                <button type="button" class="md-btn-text" wire:click="askRevert({{ $scheduled->id }})">Cancelar</button>
            </p>
        @endif
    </section>

    <p class="md-body-small game-muted mt-2 mb-0">El director y las temporadas se activan o pausan en <a href="{{ route('game.settings') }}" wire:navigate>Ajustes</a>.</p>

    <section class="md-card-elevated mt-3">
        <h2 class="md-title-medium mb-2">Historial</h2>
        @if ($history->isEmpty())
            <p class="md-body-medium game-muted mb-0">Todavía no hay temporadas.</p>
        @else
            <x-ui.list label="Historial de temporadas">
                @foreach ($history as $season)
                    @php
                        $dates = $season->starts_at ? $season->starts_at->translatedFormat('j M').' – '.$season->ends_at?->translatedFormat('j M Y') : null;
                        $supporting = collect([$season->displayStatus(), $dates, $season->errors ? implode(' ', $season->errors) : null])->filter()->implode(' · ');
                    @endphp
                    <x-ui.list-item :headline="$season->name ?: '(sin nombre)'" :supporting="$supporting" wire:key="season-{{ $season->id }}">
                        <x-slot:trailing>
                            <x-ui.badge>{{ $season->displayStatus() }}</x-ui.badge>
                        </x-slot:trailing>
                    </x-ui.list-item>
                @endforeach
            </x-ui.list>
        @endif
    </section>

    <div x-data="{ revertOpen: $wire.entangle('confirmRevert').live }">
        <x-ui.dialog standard persistent-content state="revertOpen" title="Revertir temporada" icon="bi-arrow-counterclockwise">
            <p class="md-body-large">¿Revertir esta temporada? Sus reglas dejan de aplicarse desde ahora. No pierdes nada de lo que ganaste.</p>
            <div class="d-flex gap-2 justify-content-end mt-3">
                <button type="button" class="md-btn-text" x-on:click="revertOpen = false">Cancelar</button>
                <button type="button" class="md-btn-filled" wire:click="revert">Revertir</button>
            </div>
        </x-ui.dialog>
    </div>
</x-module-shell>
