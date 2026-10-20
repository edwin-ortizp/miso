@php
    use App\Support\Ui\DataState;

    $catalogFilters = array_filter([
        $catalogSearch, $catalogCategory, $catalogSource,
        $catalogVehicleType, $catalogPowerSource, $catalogTransmissionType,
    ], static fn ($value) => $value !== '');

    $catalogState = DataState::resolve(
        visible: $catalogTemplates->count(),
        total: $catalogFilters === [] ? $catalogTemplates->total() : 1,
    );
@endphp

<x-module-shell module="vehicles" title="Catálogo de mantenimientos" subtitle="Plantillas base y personales para tu garaje." icon="bi-tools" archetype="list" :back="\App\Support\Ui\Tabs\VehicleTabs::back()">
    <x-slot:actions><x-module-actions mobile-style="inline" :primary="['label' => 'Nueva plantilla', 'icon' => 'bi-plus-lg', 'action' => 'openTemplateForm']" /></x-slot:actions>


    @if ($catalogMessage)
        <x-ui.snackbar>{{ $catalogMessage }}</x-ui.snackbar>
    @endif

    @php
        $catalogActive = count(array_filter([$catalogCategory, $catalogSource, $catalogVehicleType, $catalogPowerSource, $catalogTransmissionType], static fn ($value) => $value !== ''));
    @endphp
    <nav aria-label="Ajustes de categorías"><x-ui.action variant="text" icon="bi-diagram-3" :href="route('categories.settings', 'maintenance')">Gestionar categorías</x-ui.action></nav>
    <x-ui.management-card clear="clearCatalogFilters" id="vehicle-catalog" title="Plantillas" icon="bi-tools" :count="'('.$catalogTemplates->total().')'"
                          search="catalogSearch" search-placeholder="Buscar nombre o descripción" :active-filters="$catalogActive"
                          :paginator="$catalogTemplates" noun="plantillas" alpine="openMenu: null">
        <x-slot:filters>
            <div class="md-chip-rail md-mcard__filter-chips" role="group" aria-label="Filtros del catálogo" @click.outside="openMenu = null">
                <x-ui.filter-menu name="catalogCategory" label="Categorías" allLabel="Todas las categorías"
                                  :options="$catalogCategories"
                                  :selected="$catalogCategory" />

                <x-ui.filter-menu name="catalogSource" label="Origen" allLabel="Base y personal"
                                  :options="['base' => 'Solo base', 'personal' => 'Solo personal']"
                                  :selected="$catalogSource" />

                <x-ui.filter-menu name="catalogVehicleType" label="Vehículo" allLabel="Todos los vehículos"
                                  :options="['automovil' => 'Automóvil', 'motocicleta' => 'Motocicleta', 'bicicleta' => 'Bicicleta', 'patineta' => 'Patineta', 'otro' => 'Otro']"
                                  :selected="$catalogVehicleType" />

                <x-ui.filter-menu name="catalogPowerSource" label="Propulsión" allLabel="Toda propulsión"
                                  :options="['gasolina' => 'Gasolina', 'diesel' => 'Diésel', 'electrico' => 'Eléctrica', 'hibrido' => 'Híbrida', 'humana' => 'Humana', 'ninguna' => 'Ninguna']"
                                  :selected="$catalogPowerSource" />

                <x-ui.filter-menu name="catalogTransmissionType" label="Transmisión" allLabel="Todas las transmisiones"
                                  :options="['manual' => 'Manual', 'automatica' => 'Automática', 'cvt' => 'CVT', 'automatizada' => 'Automatizada', 'no_aplica' => 'No aplica']"
                                  :selected="$catalogTransmissionType" />
            </div>
        </x-slot:filters>
        @php
            $appliedChips = array_values(array_filter([
                $catalogCategory !== '' ? ['key' => 'catalogCategory', 'value' => null, 'label' => 'Categoría: '.($catalogCategories[$catalogCategory] ?? $catalogCategory), 'icon' => 'bi-tag'] : null,
                $catalogSource !== '' ? ['key' => 'catalogSource', 'value' => null, 'label' => 'Origen: '.($catalogSource === 'base' ? 'Solo base' : 'Solo personal'), 'icon' => 'bi-collection'] : null,
                $catalogVehicleType !== '' ? ['key' => 'catalogVehicleType', 'value' => null, 'label' => 'Vehículo: '.(['automovil' => 'Automóvil', 'motocicleta' => 'Motocicleta', 'bicicleta' => 'Bicicleta', 'patineta' => 'Patineta', 'otro' => 'Otro'][$catalogVehicleType] ?? $catalogVehicleType), 'icon' => 'bi-car-front'] : null,
                $catalogPowerSource !== '' ? ['key' => 'catalogPowerSource', 'value' => null, 'label' => 'Propulsión: '.(['gasolina' => 'Gasolina', 'diesel' => 'Diésel', 'electrico' => 'Eléctrica', 'hibrido' => 'Híbrida', 'humana' => 'Humana', 'ninguna' => 'Ninguna'][$catalogPowerSource] ?? $catalogPowerSource), 'icon' => 'bi-lightning-charge'] : null,
                $catalogTransmissionType !== '' ? ['key' => 'catalogTransmissionType', 'value' => null, 'label' => 'Transmisión: '.(['manual' => 'Manual', 'automatica' => 'Automática', 'cvt' => 'CVT', 'automatizada' => 'Automatizada', 'no_aplica' => 'No aplica'][$catalogTransmissionType] ?? $catalogTransmissionType), 'icon' => 'bi-gear'] : null,
            ]));
        @endphp
        @if ($appliedChips !== [])
            <x-slot:strip>
                <x-ui.applied-filters :filters="$appliedChips" />
            </x-slot:strip>
        @endif
        @if ($catalogActive)
            <x-slot:filterActions>
                <x-ui.action variant="outlined" icon="bi-eraser" wire:click="clearCatalogFilters" x-on:click="filtersOpen = false">Limpiar</x-ui.action>
                <x-ui.action variant="filled" icon="bi-funnel-fill" x-on:click="filtersOpen = false">Filtrar</x-ui.action>
            </x-slot:filterActions>
        @endif

        @if ($catalogState === DataState::CONTENT)
            <div class="vehicle-catalog-grid">
                @foreach ($catalogTemplates as $template)
                    <article class="md-card-outlined" wire:key="template-{{ $template->id }}">
                        <header>
                            <div>
                                <span>{{ $template->category_label }}</span>
                                <h3>{{ $template->name }}</h3>
                            </div>
                            <small>{{ $template->user_id ? 'Personal' : 'Base' }}</small>
                        </header>
                        <p>{{ $template->description ?: 'Sin descripción.' }}</p>
                        <div class="vehicle-catalog-card__footer">
                            <span>{{ $template->default_interval_days ? 'Cada '.$template->default_interval_days.' días' : 'Sin periodo' }}{{ $template->default_interval_days && $template->default_interval_usage ? ' · ' : '' }}{{ $template->default_interval_usage ? number_format($template->default_interval_usage, 0, ',', '.').' de uso' : '' }}</span>
                            @if ($template->user_id)
                                <div>
                                    <x-ui.icon-action icon="bi-pencil" label="Editar la plantilla {{ $template->name }}" size="sm"
                                                      wire:click="openTemplateForm('{{ $template->id }}')" />
                                    <x-ui.destructive-action label="Eliminar la plantilla {{ $template->name }}" :iconOnly="true" size="sm"
                                                             action="deleteTemplate('{{ $template->id }}')"
                                                             title="Eliminar plantilla"
                                                             message="La plantilla «{{ $template->name }}» se elimina de forma permanente." />
                                </div>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @elseif ($catalogState === DataState::FILTERED_EMPTY)
            <x-ui.state variant="filtered-empty" icon="bi-tools" message="Hay plantillas en el catálogo, pero ninguna coincide con los filtros activos.">
                <x-slot:actions>
                    <x-ui.action variant="outlined" icon="bi-x-circle" wire:click="clearCatalogFilters">Limpiar filtros</x-ui.action>
                </x-slot:actions>
            </x-ui.state>
        @else
            <x-ui.state variant="empty" icon="bi-tools" title="No hay plantillas disponibles"
                        message="Crea una plantilla para reutilizarla en los cuidados de tu garaje.">
            </x-ui.state>
        @endif
    </x-ui.management-card>

    @include('livewire.vehicle.partials.template-form')
</x-module-shell>
