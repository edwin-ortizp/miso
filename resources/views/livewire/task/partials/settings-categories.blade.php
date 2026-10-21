<form wire:submit="saveGlobalPrefix" class="d-flex flex-wrap align-items-end gap-2 mb-3">
    <x-ui.field name="globalCodePrefix" label="Prefijo para tareas sin categoría" maxlength="5" wire:model="globalCodePrefix" />
    <button type="submit" class="md-btn-text">Guardar prefijo</button>
</form>
@php
    use App\Support\Ui\DataState;

    $state = DataState::resolve($paginator->total(), $catalogTotal);
@endphp

<x-ui.management-card id="task-settings-categories" title="Categorías de tareas" icon="bi-tags"
                      :count="$paginator->total()" search="q" search-placeholder="Buscar categoría"
                      :paginator="$paginator" noun="categorías">
    @if ($state === DataState::CONTENT)
        <table class="md-table md-table--stack" aria-label="Catálogo de categorías de tareas">
            <thead>
                <tr>
                    <th scope="col">Nombre</th>
                    <th scope="col">Prefijo</th>
                    <th scope="col">Pendientes</th>
                    <th scope="col">Total</th>
                    <th scope="col" class="md-table__actions"><span class="visually-hidden">Acciones</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($paginator as $category)
                    @php
                        $row = $counts[$category->key] ?? null;
                        $pending = (int) ($row->pending ?? 0);
                        $total = (int) ($row->total ?? 0);
                    @endphp
                    <tr wire:key="task-category-{{ $category->id }}">
                        <td data-label="Nombre" class="md-table__title"><i class="bi {{ $category->icon ?: 'bi-tag' }}" aria-hidden="true"></i> {{ $category->path_label }}</td>
                        <td data-label="Prefijo"><code>{{ $category->code_prefix }}</code></td>
                        <td data-label="Pendientes" class="md-table__nowrap">{{ $pending }}</td>
                        <td data-label="Total" class="md-table__nowrap">{{ $total }}</td>
                        <td data-label="Acciones" class="md-table__actions">
                            <x-ui.row-actions :label="'Acciones de '.$category->name">
                                <x-slot:primary wire:click="openForm('{{ $category->id }}')">Editar</x-slot:primary>
                                @if ($canMoveUp[$category->id] ?? false)
                                    <x-ui.menu-item icon="bi-arrow-up" wire:click="move('{{ $category->id }}', -1)">Subir</x-ui.menu-item>
                                @endif
                                @if ($canMoveDown[$category->id] ?? false)
                                    <x-ui.menu-item icon="bi-arrow-down" wire:click="move('{{ $category->id }}', 1)">Bajar</x-ui.menu-item>
                                @endif
                                <x-ui.menu-divider />
                                @if ($row)
                                    <x-ui.menu-item icon="bi-trash" tone="danger" wire:click="delete('{{ $category->id }}')">Eliminar</x-ui.menu-item>
                                @else
                                    <x-ui.menu-item icon="bi-trash" tone="danger" wire:click="delete('{{ $category->id }}')"
                                                    wire:confirm="«{{ $category->path_label }}» no tiene tareas; se eliminará de tu catálogo.">Eliminar</x-ui.menu-item>
                                @endif
                            </x-ui.row-actions>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @elseif ($state === DataState::FILTERED_EMPTY)
        <x-ui.state variant="filtered-empty" message="Prueba otra búsqueda para encontrar tu categoría." />
    @else
        <x-ui.state variant="empty" icon="bi-tags" title="Aún no tienes categorías"
                    message="Crea la primera desde el botón flotante para clasificar tus tareas." />
    @endif
</x-ui.management-card>

@if ($uncategorized)
    <p class="md-body-small mt-3 mb-0">
        {{ (int) $uncategorized->pending }} {{ (int) $uncategorized->pending === 1 ? 'tarea pendiente' : 'tareas pendientes' }} sin categoría.
    </p>
@endif

<x-ui.form-dialog :open="$showForm" close="closeForm" submit-action="save" id="task-category-dialog"
                  :title="$editingId ? 'Editar categoría' : 'Nueva categoría'" icon="bi-tags"
                  :submit="$editingId ? 'Actualizar' : 'Crear categoría'">
    <div class="d-flex flex-column gap-3">
        <x-ui.field name="name" label="Nombre" :required="true" maxlength="60" wire:model="name" />
        <x-ui.field name="codePrefix" label="Prefijo" maxlength="5" help="Deja vacío para sugerirlo a partir del nombre. Los códigos existentes se conservan." wire:model="codePrefix" />
        <x-ui.category-parent model="TaskCategory" :editing-id="$editingId" />
        <x-ui.select name="icon" label="Icono" :selected="$icon" :options="$icons" :icon="$icon" wire:model.live="icon" />
    </div>
</x-ui.form-dialog>

<x-ui.form-dialog :open="(bool) $deleting" close="cancelDelete" submit-action="reassignAndDelete" id="task-category-delete-dialog"
                  :title="$deleting ? 'Eliminar «'.$deleting->name.'»' : 'Eliminar categoría'" icon="bi-exclamation-triangle"
                  submit="Eliminar categoría">
    @if ($deleting)
        <div class="d-flex flex-column gap-3">
            <p class="md-body-medium mb-0">
                Hay {{ $deletingCount }} {{ $deletingCount === 1 ? 'tarea' : 'tareas' }} con esta categoría.
                Elige a cuál pasarlas o déjalas sin categoría.
            </p>
            <x-ui.select name="reassignTo" label="Pasar tareas a" placeholder="Sin categoría"
                         :selected="$reassignTo" :options="$reassignOptions" wire:model="reassignTo" />
        </div>
    @endif
</x-ui.form-dialog>
