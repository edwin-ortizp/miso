@php
    use App\Support\Ui\DataState;

    $isFiltered = count($activeFilters) > 0 || trim($search) !== '' || $onlyFavorites;
    $typesState = $section !== 'drinks' ? null : ($drinkTypes->count() > 0
        ? DataState::CONTENT
        : ($totalTypes > 0 && $isFiltered ? DataState::FILTERED_EMPTY : DataState::EMPTY));
@endphp

<x-module-shell module="water" title="Ajustes de hidratación" archetype="settings">
    @if ($section !== 'goal')
        <x-slot:actions>
            <x-module-actions :primary="$section === 'categories'
                ? ['label' => 'Crear categoría', 'icon' => 'bi-plus-lg', 'action' => 'openCategoryForm']
                : ['label' => 'Nueva bebida', 'icon' => 'bi-plus-lg', 'action' => 'openForm']" />
        </x-slot:actions>
    @endif

    @if ($message || $categoryMessage)
        <div class="md-card-filled mb-3 py-3" role="status" aria-live="polite">{{ $message ?: $categoryMessage }}</div>
    @endif

    <div class="module-settings-workspace">
        <div>
        @if ($section === 'categories')
            @include('livewire.category.partials.section')
        @elseif ($section === 'goal')
            <x-context-widget title="Meta diaria" icon="bi-bullseye">
                <p class="md-headline-small mb-0"><strong>{{ number_format($goal, 0, ',', '.') }}</strong> <span class="md-body-medium">ml al día</span></p>
                <p class="md-body-small mb-3">≈ {{ number_format(round($goal / 250), 0, ',', '.') }} vasos de 250 ml</p>
                <form wire:submit="saveGoal" class="d-grid gap-2" novalidate>
                    <x-ui.field name="dailyWaterGoal" label="Nueva meta (ml)" type="number" min="500" max="10000" step="50" wire:model="dailyWaterGoal" />
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="md-btn-filled"><i class="bi bi-floppy" aria-hidden="true"></i><span>Guardar</span></button>
                    </div>
                </form>
            </x-context-widget>
        @else
    <x-ui.management-card id="water-drink-types" title="Tipos de bebida" icon="bi-cup-straw"
                          :count="'('.$drinkTypes->total().' / '.$totalTypes.')'" search="search" search-placeholder="Buscar bebida"
                          :active-filters="count($activeFilters)" :paginator="$drinkTypes" noun="bebidas"
                          sort-model="sort" :sort-options="$sorts" :sort-value="$sort"
                          alpine="draftFactor: 'all', draftUsage: 'all'"
                          on-filters-open="draftFactor = $wire.factor; draftUsage = $wire.usage">
        <x-slot:filters>
            <x-ui.select name="filterFactor" label="Factor de hidratación" :options="$factors" :selected="$factor" icon="bi-droplet-half" x-model="draftFactor" />
            <x-ui.select name="filterUsage" label="Uso" :options="$usages" :selected="$usage" icon="bi-clock-history" x-model="draftUsage" />
        </x-slot:filters>
        <x-slot:filterActions>
            <x-ui.action variant="outlined" icon="bi-eraser" wire:click="clearFilters" x-on:click="filtersOpen = false">Limpiar</x-ui.action>
            <x-ui.action variant="filled" icon="bi-funnel-fill" x-on:click="$wire.applyFilters(draftFactor, draftUsage); filtersOpen = false">Filtrar</x-ui.action>
        </x-slot:filterActions>

        @if (count($activeFilters) > 0)
            <x-slot:strip>
                <x-ui.applied-filters :filters="$activeFilters" />
            </x-slot:strip>
        @endif

        <div class="md-favorites-bar" role="group" aria-label="Favoritas y sugeridas">
            <x-ui.chip variant="filter" icon="bi-star" :selected="$onlyFavorites" wire:click="$toggle('onlyFavorites')">Solo favoritas</x-ui.chip>
            @foreach ($suggestions as $suggestion)
                <x-ui.chip variant="suggestion" icon="bi-star" wire:key="suggest-{{ $suggestion->id }}" wire:click="toggleFavorite('drinks', '{{ $suggestion->id }}')"
                           title="Más usada: agregar a favoritas" aria-label="Agregar {{ $suggestion->name }} a favoritas, es de las más usadas">{{ $suggestion->name }}</x-ui.chip>
            @endforeach
            @if ($suggestions->isNotEmpty())<span class="md-body-small md-favorites-bar__hint">Sugeridas por tu uso</span>@endif
        </div>
        @if ($typesState === DataState::CONTENT)
            <table class="md-table md-table--stack">
                <thead>
                    <tr>
                        <th scope="col">Bebida</th>
                        <th scope="col">Factor</th>
                        <th scope="col">Registros</th>
                        <th scope="col" class="md-table__actions"><span class="visually-hidden">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($drinkTypes as $type)
                        <tr wire:key="drink-type-{{ $type->id }}">
                            <td class="md-table__title">
                                <x-ui.favorite-toggle :favorite="in_array($type->id, $favoriteIds, true)" :label="$type->name" wire:click="toggleFavorite('drinks', '{{ $type->id }}')" />
                                <span aria-hidden="true">{{ $type->icon ?: '💧' }}</span> {{ $type->name }}
                            </td>
                            <td class="md-table__nowrap">×{{ number_format((float) $type->hydration_factor, 2, ',', '.') }}</td>
                            <td class="md-table__nowrap">{{ $type->logs_count ? number_format($type->logs_count, 0, ',', '.') : 'Sin registros' }}</td>
                            <td class="md-table__actions">
                                <x-ui.row-actions :label="'Más acciones de '.$type->name">
                                    <x-slot:primary wire:click="openForm('{{ $type->id }}')">Editar</x-slot:primary>
                                    <x-ui.menu-divider />
                                    @if ($type->logs_count)
                                        <x-ui.menu-item icon="bi-trash" tone="danger" disabled aria-disabled="true"
                                                        title="Tiene registros históricos; no se puede eliminar">Eliminar</x-ui.menu-item>
                                    @else
                                        <x-ui.menu-item icon="bi-trash" tone="danger" wire:click="delete('{{ $type->id }}')"
                                                        wire:confirm="La bebida «{{ $type->name }}» se elimina de forma permanente.">Eliminar</x-ui.menu-item>
                                    @endif
                                </x-ui.row-actions>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @elseif ($typesState === DataState::FILTERED_EMPTY)
            <x-ui.state variant="{{ DataState::FILTERED_EMPTY }}" message="Prueba con otra búsqueda o quita algún filtro." />
        @else
            <x-ui.state variant="{{ DataState::EMPTY }}" icon="bi-cup-straw" title="Aún no tienes tipos de bebida"
                        message="Crea una bebida para usarla en tus registros de hidratación." />
        @endif
    </x-ui.management-card>
        @endif
        </div>
        <x-settings-sections label="Secciones de Ajustes de hidratación" :items="$sections" :current="$section" />
    </div>

    @if ($section === 'drinks')
    <x-ui.form-dialog :open="$showForm" close="closeForm" submit-action="save" id="water-drink-type-dialog"
                      :title="$editingId ? 'Editar bebida' : 'Nueva bebida'" icon="bi-cup-straw" submit="Guardar">
        <div class="d-flex flex-column gap-3">
            <x-ui.field name="icon" label="Ícono" :required="true" maxlength="40" help="Un emoji basta." wire:model="icon" />
            <x-ui.field name="name" label="Nombre" :required="true" maxlength="255" wire:model="name" />
        <x-ui.category-select name="category" label="Categoría" placeholder="Sin categoría" :options="\App\Support\CategoryCatalog::options('drinks')" wire:model="category" />
            <x-ui.field name="hydrationFactor" label="Factor de hidratación" type="number" :required="true" min="0" max="9.99" step="0.01"
                        help="Multiplica los ml registrados: 1,00 hidrata igual que el agua." wire:model="hydrationFactor" />
        </div>
    </x-ui.form-dialog>
    @endif
</x-module-shell>
