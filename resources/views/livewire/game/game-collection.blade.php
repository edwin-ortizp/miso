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
                                 :options="collect($types)->mapWithKeys(fn ($t) => [$t => \App\Services\Game\Albums::label($t)])->all()" :selected="$type" wire:model.live="type" />
                    <x-ui.select name="game-filter-rarity" label="Rareza" placeholder="Todas las rarezas" icon="bi-star"
                                 :options="collect($rarities)->mapWithKeys(fn ($r) => [$r => config('game.rarity_labels.'.$r, $r)])->all()" :selected="$rarity" wire:model.live="rarity" />
                </div>
            </x-slot:filters>
            @php
                $appliedChips = array_values(array_filter([
                    $state !== 'all' ? ['key' => 'state', 'value' => null, 'label' => $states[$state], 'icon' => 'bi-funnel'] : null,
                    $type !== '' ? ['key' => 'type', 'value' => null, 'label' => 'Álbum '.\App\Services\Game\Albums::label($type), 'icon' => 'bi-tag'] : null,
                    $rarity !== '' ? ['key' => 'rarity', 'value' => null, 'label' => 'Rareza: '.config("game.rarity_labels.{$rarity}", $rarity), 'icon' => 'bi-star'] : null,
                ]));
            @endphp
            @if ($appliedChips !== [])
                <x-slot:strip>
                    <x-ui.applied-filters :filters="$appliedChips" />
                </x-slot:strip>
            @endif

            <x-ui.progress :value="$discoveredCount" :max="$total" label="Progreso de la colección" />

            @if ($albums !== [])
                {{-- CHG-078: un álbum por tipo; tocarlo filtra la cuadrícula por ese tipo (?type=). --}}
                @php $activeAlbum = collect($albums)->firstWhere('type', $type); @endphp
                <nav class="game-albums" aria-label="Álbumes">
                    <h2 class="md-title-small mb-2">Álbumes</h2>
                    <div class="game-albums__rail" role="group" aria-label="Álbumes por tipo">
                        <button type="button" class="game-album" aria-pressed="{{ $type === '' ? 'true' : 'false' }}" wire:click="$set('type', '')">
                            <span class="game-album__name">Todos</span>
                        </button>
                        @foreach ($albums as $album)
                            <button type="button" wire:key="album-{{ $album['type'] }}"
                                    @class(['game-album', 'game-type--'.$album['type'], 'game-album--complete' => $album['complete']])
                                    aria-pressed="{{ $type === $album['type'] ? 'true' : 'false' }}"
                                    aria-label="Álbum {{ $album['label'] }}: {{ $album['found'] }} de {{ $album['total'] }} especies{{ $album['complete'] ? ', completo' : '' }}"
                                    wire:click="$set('type', '{{ $album['type'] }}')">
                                <span class="game-album__name">{{ $album['label'] }}</span>
                                <span class="game-album__count">{{ $album['found'] }} de {{ $album['total'] }}</span>
                                @if ($album['complete'])
                                    <span class="game-album__seal"><i class="bi bi-patch-check-fill" aria-hidden="true"></i> Completo</span>
                                @endif
                                <x-ui.progress class="game-album__bar" :value="$album['found']" :max="$album['total']" />
                            </button>
                        @endforeach
                    </div>
                    @if ($activeAlbum)
                        <p class="md-title-small mt-2 mb-0">Álbum {{ $activeAlbum['label'] }} · {{ $activeAlbum['found'] }} de {{ $activeAlbum['total'] }}</p>
                    @endif
                </nav>
            @endif

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
