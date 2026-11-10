<x-module-shell module="game">
    @if ($total === 0)
        <section class="md-card-elevated">
            <x-ui.state variant="empty" icon="bi-grid-3x3-gap" title="Aún no hay especies"
                        message="Importa tu catálogo para empezar." />
        </section>
    @else
        <x-ui.management-card id="game-collection" title="Colección" icon="bi-grid-3x3-gap"
                              :count="$discoveredCount.' de '.$total.' descubiertas'"
                              search="search" search-placeholder="Buscar nombre o número"
                              :active-filters="$activeFilters" :paginator="$entries" noun="especies"
                              :per-page-options="\App\Livewire\Game\GameCollection::perPageOptions()">
            <x-slot:filters>
                <div class="md-chip-rail md-mcard__filter-chips" role="group" aria-label="Estado">
                    @foreach ($states as $key => $label)
                        <x-ui.chip variant="filter" :selected="$state === $key" wire:click="$set('state', '{{ $key }}')">{{ $label }}</x-ui.chip>
                    @endforeach
                </div>
                <div class="game-filters mt-2">
                    <x-ui.select name="game-filter-type" label="Tipo" placeholder="Todos los tipos" icon="bi-tag"
                                 :options="collect($types)->mapWithKeys(fn ($t) => [$t => $t])->all()" :selected="$type" wire:model.live="type" />
                    <x-ui.select name="game-filter-rarity" label="Rareza" placeholder="Todas las rarezas" icon="bi-star"
                                 :options="collect($rarities)->mapWithKeys(fn ($r) => [$r => config('game.rarity_labels.'.$r, $r)])->all()" :selected="$rarity" wire:model.live="rarity" />
                </div>
            </x-slot:filters>
            @php
                $appliedChips = array_values(array_filter([
                    $state !== 'all' ? ['key' => 'state', 'value' => null, 'label' => $states[$state], 'icon' => 'bi-funnel'] : null,
                    $type !== '' ? ['key' => 'type', 'value' => null, 'label' => 'Tipo: '.$type, 'icon' => 'bi-tag'] : null,
                    $rarity !== '' ? ['key' => 'rarity', 'value' => null, 'label' => 'Rareza: '.config("game.rarity_labels.{$rarity}", $rarity), 'icon' => 'bi-star'] : null,
                ]));
            @endphp
            @if ($appliedChips !== [])
                <x-slot:strip>
                    <x-ui.applied-filters :filters="$appliedChips" />
                </x-slot:strip>
            @endif

            <x-ui.progress :value="$discoveredCount" :max="$total" label="Progreso de la colección" />

            @if ($entries->isEmpty())
                <x-ui.state variant="filtered-empty" title="Ningún resultado con estos filtros" />
            @else
                <ul class="game-grid mt-3" aria-label="Especies">
                    @foreach ($entries as $entry)
                        <li wire:key="species-{{ $entry['species']->id }}">
                            <x-game.species-card :species="$entry['species']" :discovered="$entry['discovered']" :image="$entry['image']"
                                                 :in-team="$entry['inTeam']" wire:click="showSpecies({{ $entry['species']->id }})" />
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.management-card>
    @endif

    <div x-data="{ sheetOpen: $wire.entangle('showSheet').live }">
        <x-ui.dialog standard persistent-content state="sheetOpen" title="Ficha" size="lg" icon="bi-journal-richtext" data-module="game"
                     :sections="[
                        'profile' => ['label' => 'Ficha', 'icon' => 'bi-person-badge'],
                        ...($sheet && $sheet['discovered'] && $sheet['companion'] ? ['history' => ['label' => 'Historial', 'icon' => 'bi-clock-history']] : []),
                     ]">
            @if ($sheet)
                @include('livewire.game.partials.species-sheet', ['sheet' => $sheet])
            @endif
        </x-ui.dialog>
    </div>
</x-module-shell>
