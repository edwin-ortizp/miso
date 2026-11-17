{{-- Sección «Categorías» de los ajustes de un módulo. Requiere el trait ManagesUserCategories. --}}
<x-ui.management-card :id="'category-catalog-'.$categoryDomain" :title="'Categorías · '.$categoryLabel" icon="bi-diagram-3"
                      :count="'('.$categoryRecords->count().')'" search="categorySearch" search-placeholder="Buscar categorías">
    @if ($categoryRecords->isEmpty())
        <x-ui.state variant="empty" icon="bi-diagram-3" title="Aún no hay categorías"
                    message="Crea una categoría para agrupar los elementos de este catálogo." />
    @else
        <table class="md-table md-table--stack" aria-label="Categorías">
            <thead>
                <tr>
                    <th scope="col">Nombre</th>
                    <th scope="col">Categoría padre</th>
                    <th scope="col" class="md-table__actions"><span class="visually-hidden">Acciones</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($categoryRecords as $record)
                    <tr wire:key="category-{{ $record->id }}">
                        <td class="md-table__title">@if ($record->parent_id)<span aria-hidden="true">↳ </span>@endif{{ $record->name }}</td>
                        <td>{{ $record->parent?->name ?? 'Sin padre' }}</td>
                        <td class="md-table__actions">
                            <x-ui.row-actions :label="'Más acciones de '.$record->name">
                                <x-slot:primary wire:click="openCategoryForm('{{ $record->id }}')">Editar</x-slot:primary>
                                <x-ui.menu-divider />
                                <x-ui.menu-item icon="bi-trash" tone="danger" wire:click="deleteCategory('{{ $record->id }}')"
                                                wire:confirm="¿Eliminar la categoría «{{ $record->name }}»?">Eliminar</x-ui.menu-item>
                            </x-ui.row-actions>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</x-ui.management-card>

<x-ui.form-dialog :open="$showCategoryForm" close="closeCategoryForm" submit-action="saveCategory" id="category-form"
                  :title="$categoryEditingId ? 'Editar categoría' : 'Crear categoría'" icon="bi-diagram-3" submit="Guardar">
    <div class="d-flex flex-column gap-3">
        <x-ui.field name="categoryName" label="Nombre" wire:model="categoryName" :required="true" maxlength="100" />
        <x-ui.category-select name="categoryParentId" label="Categoría padre" placeholder="Sin padre" :options="$categoryParents"
                              help="Solo se permiten categorías padre e hijas." wire:model="categoryParentId" />
    </div>
</x-ui.form-dialog>
