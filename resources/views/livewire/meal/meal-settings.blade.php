@php
    $labels = [
        'stores' => ['title' => 'Tiendas', 'icon' => 'bi-shop', 'noun' => 'tiendas', 'one' => 'tienda', 'create' => 'Crear tienda', 'search' => 'Buscar tiendas'],
        'categories' => ['title' => 'Categorías de productos', 'icon' => 'bi-tags', 'noun' => 'categorías', 'one' => 'categoría', 'create' => 'Crear categoría', 'search' => 'Buscar categorías'],
        'locations' => ['title' => 'Ubicaciones', 'icon' => 'bi-geo-alt', 'noun' => 'ubicaciones', 'one' => 'ubicación', 'create' => 'Crear ubicación', 'search' => 'Buscar ubicaciones'],
    ][$section];
@endphp
<x-module-shell module="meals" title="Ajustes de Comidas" archetype="settings">
    <x-slot:actions>
        <x-module-actions :primary="['label' => $labels['create'], 'icon' => 'bi-plus-lg', 'action' => 'openForm']" />
    </x-slot:actions>
    @if ($message)<div class="md-card-filled mb-3 py-3" role="status" aria-live="polite">{{ $message }}</div>@endif
    <div class="meal-settings-workspace">
        <div>
            <x-ui.management-card id="meal-settings-catalog" :title="$labels['title']" :icon="$labels['icon']"
                                  :count="'('.$records->total().')'" search="search" :search-placeholder="$labels['search']" :paginator="$records" :noun="$labels['noun']">
                @if ($section === 'locations')
                    <div class="d-flex gap-2 mb-3" role="group" aria-label="Estado de las ubicaciones">
                        <x-ui.chip variant="filter" :selected="! $showArchived" wire:click="$set('showArchived', false)">Activas</x-ui.chip>
                        <x-ui.chip variant="filter" icon="bi-archive" :selected="$showArchived" wire:click="$set('showArchived', true)">Archivadas</x-ui.chip>
                    </div>
                @endif
                @if ($records->isEmpty())
                    <x-ui.state variant="empty" :icon="$labels['icon']" title="No hay resultados" :message="$section === 'locations' && ! $showArchived && $search === '' ? 'Crea lugares como Cocina o Patio y, dentro de ellos, la nevera o las alacenas.' : 'Prueba otra búsqueda o crea un registro desde el botón flotante.'" />
                @else
                    <x-ui.list :label="$labels['title'].' del catálogo'">
                        @foreach ($records as $record)
                            @php
                                $supporting = match ($section) {
                                    'stores' => $record->prices_count.' precios · '.$record->purchases_count.' compras',
                                    'locations' => ($locationCounts[$record->id] ?? 0).' productos',
                                    default => ($categoryCounts[$record->id] ?? 0).' ingredientes',
                                };
                                $archivedLocation = $section === 'locations' && $record->isArchived();
                            @endphp
                            <x-ui.list-item :headline="$section === 'stores' ? $record->name : $record->path_label" :supporting="$supporting" wire:key="meal-setting-{{ $record->id }}">
                                <x-slot:trailing>
                                    <x-ui.row-actions :label="'Acciones de '.$record->name">
                                        <x-slot:primary wire:click="{{ $archivedLocation ? 'restore' : 'openForm' }}('{{ $record->id }}')">{{ $archivedLocation ? 'Restaurar' : 'Editar' }}</x-slot:primary>
                                        @if ($section === 'stores')<x-ui.menu-item icon="bi-intersect" wire:click="openMerge('{{ $record->id }}')">Fusionar con otra tienda</x-ui.menu-item>@endif
                                        @if ($section === 'locations')
                                            @unless ($archivedLocation)
                                                <x-ui.menu-item icon="bi-archive" wire:click="archive('{{ $record->id }}')" wire:confirm="¿Archivar «{{ $record->path_label }}»{{ $record->parent_id ? '' : ' y sus sububicaciones' }}? Los productos conservarán su ubicación.">Archivar</x-ui.menu-item>
                                            @endunless
                                        @else
                                            <x-ui.menu-item icon="bi-trash" tone="danger" wire:click="delete('{{ $record->id }}')" wire:confirm="¿Eliminar «{{ $record->path_label ?? $record->name }}»?">Eliminar</x-ui.menu-item>
                                        @endif
                                    </x-ui.row-actions>
                                </x-slot:trailing>
                            </x-ui.list-item>
                        @endforeach
                    </x-ui.list>
                @endif
            </x-ui.management-card>
        </div>
        <nav class="meal-settings-nav" aria-label="Secciones de Ajustes de Comidas">
            <a href="{{ route('meals.settings') }}" wire:navigate class="meal-settings-nav__item {{ $section === 'stores' ? 'is-active' : '' }}" @if ($section === 'stores') aria-current="page" @endif><i class="bi bi-shop" aria-hidden="true"></i> Tiendas</a>
            <a href="{{ route('meals.settings.categories') }}" wire:navigate class="meal-settings-nav__item {{ $section === 'categories' ? 'is-active' : '' }}" @if ($section === 'categories') aria-current="page" @endif><i class="bi bi-tags" aria-hidden="true"></i> Categorías de productos</a>
            <a href="{{ route('meals.settings.locations') }}" wire:navigate class="meal-settings-nav__item {{ $section === 'locations' ? 'is-active' : '' }}" @if ($section === 'locations') aria-current="page" @endif><i class="bi bi-geo-alt" aria-hidden="true"></i> Ubicaciones</a>
        </nav>
    </div>
    <x-ui.form-dialog :open="$showForm" close="closeForm" submit-action="save" id="meal-settings-form" :title="($editingId ? 'Editar ' : 'Crear ').$labels['one']" :icon="$labels['icon']" :submit="$confirmSimilar ? 'Crear de todos modos' : 'Guardar'">
        <x-ui.field name="name" label="Nombre" :required="true" maxlength="255" wire:model.live.debounce.300ms="name" />
        @if ($section === 'categories')
        <x-ui.category-parent model="MealCategory" :editing-id="$editingId" />
        @elseif ($section === 'locations')
        <x-ui.category-select name="parent_id" label="Dentro de" placeholder="Ninguna (ubicación principal)" :options="$locationParents" wire:model="parent_id" help="Por ejemplo, la Nevera va dentro de Cocina. Solo se permiten dos niveles." />
        @endif
    </x-ui.form-dialog>
    <x-ui.form-dialog :open="$showMerge" close="closeMerge" submit-action="merge" id="meal-store-merge" title="Fusionar tiendas" icon="bi-intersect" submit="Fusionar">
        <p>Los precios, compras y marcas de la tienda de origen pasarán a la tienda elegida. La tienda de origen se eliminará.</p>
        <x-ui.select name="mergeTargetId" label="Tienda que se conserva" placeholder="Selecciona una tienda" :required="true" :options="$storeOptions" :selected="$mergeTargetId" wire:model="mergeTargetId" />
    </x-ui.form-dialog>
</x-module-shell>
