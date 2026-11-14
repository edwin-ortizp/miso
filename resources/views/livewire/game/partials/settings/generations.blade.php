@php($activeTotal = collect($generations)->where('enabled', true)->sum('total'))
<section class="md-card-filled game-generations-summary" aria-labelledby="game-generations-title">
    <i class="bi bi-compass" aria-hidden="true"></i>
    <div>
        <h2 class="md-title-medium mb-1" id="game-generations-title">Tu aventura incluye {{ $activeTotal }} Pokémon</h2>
        <p class="md-body-medium mb-0">Elige a quiénes quieres conocer. Tú decides cuándo ampliar tu aventura.</p>
        <p class="md-body-small mb-0">Tus compañeros y las Pokébolas que ya ganaste se conservan.</p>
    </div>
</section>
<div>
    @error('enabledGenerations') <p class="game-error" role="alert">{{ $message }}</p> @enderror
    @error('enabledGenerations.*') <p class="game-error" role="alert">{{ $message }}</p> @enderror
    <div class="game-generation-grid" role="group" aria-label="Generaciones disponibles">
        @foreach ($generations as $generation)
            @php($selected = in_array($generation['generation'], $enabledGenerations))
            <section @class(['md-card-outlined', 'game-generation', 'is-selected' => $selected]) wire:key="generation-{{ $generation['generation'] }}"
                     aria-labelledby="generation-title-{{ $generation['generation'] }}">
                <div class="game-generation__heading">
                    <div>
                        <h3 class="md-title-medium mb-1" id="generation-title-{{ $generation['generation'] }}">{{ $generation['name'] }}</h3>
                        <p class="md-body-small mb-0">{{ $generation['generation'] === 1 ? 'Los 151 originales' : $generation['total'].' Pokémon' }}</p>
                    </div>
                    @if ($generation['complete'])<x-ui.badge tone="success">Completa</x-ui.badge>@endif
                </div>
                <div class="game-generation__art" aria-hidden="true">
                    @foreach ($generation['images'] as $preview)
                        @if ($preview['url'])
                            <img src="{{ $preview['url'] }}" alt="" width="72" height="72" loading="lazy">
                        @else
                            <i class="bi bi-circle-half md-title-large" aria-hidden="true"></i>
                        @endif
                    @endforeach
                </div>
                <div class="game-generation__progress">
                    <x-ui.progress :value="$generation['found']" :max="max(1, $generation['total'])"
                                   :label="'Descubiertos '.$generation['found'].' de '.$generation['total']" />
                    <span class="md-body-small game-muted">{{ $generation['total'] ? '#'.$generation['min'].'–#'.$generation['max'] : 'Sin especies disponibles' }}</span>
                </div>
                <x-ui.switch name="generation-{{ $generation['generation'] }}" :label="$selected ? 'Activa' : 'En pausa'"
                             :aria-label="$generation['name'].': '.($selected ? 'Activa' : 'En pausa')"
                             wire:model.live="enabledGenerations" :value="$generation['generation']" :checked="$selected" :disabled="$generation['total'] === 0" />
            </section>
        @endforeach
    </div>
</div>
