<x-ui.form-dialog module="vehicles" :open="$showCategoryManager" close="closeCategoryManager" submit-action="addCategory" id="vehicle-expense-categories-dialog"
                  title="Categorías de gastos" icon="bi-tags" submit="Agregar categoría">
    <div class="d-flex flex-column gap-3">
        <p class="md-body-small mb-0">Se comparten entre todos tus vehículos. Para eliminar una categoría con gastos, primero pasa sus gastos a otra.</p>

        @if ($categoryMessage)
            <p class="md-body-medium mb-0" role="status" aria-live="polite">{{ $categoryMessage }}</p>
        @endif

        <x-ui.field name="newCategoryName" label="Nueva categoría" maxlength="60" icon="bi-plus-lg" wire:model="newCategoryName" />
        @if (!$renamingCategoryId)<x-ui.category-parent model="VehicleExpenseCategory" />@endif

        @if ($deletingCategory)
            <div class="md-card-filled vehicle-category-reassign" role="group" aria-label="Eliminar {{ $deletingCategory->name }}">
                <p class="md-body-medium mb-0">«{{ $deletingCategory->name }}» tiene {{ $deletingCategory->expenses_count }} {{ $deletingCategory->expenses_count === 1 ? 'gasto' : 'gastos' }}. Elige a qué categoría pasarlos antes de eliminarla.</p>
                <x-ui.select name="reassignCategoryTo" label="Pasar los gastos a" placeholder="Seleccionar..." :required="true"
                             :options="collect($categoryOptions)->except($deletingCategory->id)->all()" :selected="$reassignCategoryTo" wire:model="reassignCategoryTo" />
                <div class="vehicle-category-reassign__actions">
                    <x-ui.action variant="text" wire:click="cancelCategoryDelete">Cancelar</x-ui.action>
                    <x-ui.action variant="filled" tone="danger" icon="bi-trash" wire:click="reassignAndDeleteCategory">Reasignar y eliminar</x-ui.action>
                </div>
            </div>
        @endif

        @if ($categories->isNotEmpty())
            <ul class="vehicle-category-list" aria-label="Tus categorías de gastos">
                @foreach ($categories as $category)
                    <li class="vehicle-category-row" wire:key="expense-category-{{ $category->id }}">
                        @if ($renamingCategoryId === $category->id)
                            <span class="vehicle-category-row__icon" aria-hidden="true"><i class="bi {{ $category->icon ?: 'bi-tag' }}"></i></span>
                            <div class="vehicle-category-row__body">
                                <x-ui.field name="renamingCategoryName" label="Nombre de la categoría" maxlength="60" wire:model="renamingCategoryName" wire:keydown.enter.prevent="saveCategoryRename" />
                                <x-ui.category-parent model="VehicleExpenseCategory" :editing-id="$renamingCategoryId" />
                            </div>
                            <x-ui.icon-action icon="bi-check-lg" label="Guardar nombre" size="sm" wire:click="saveCategoryRename" />
                            <x-ui.icon-action icon="bi-x-lg" label="Cancelar cambio de nombre" size="sm" wire:click="cancelCategoryRename" />
                        @else
                            <div class="vehicle-category-row__body">
                                <x-ui.catalog-chip tone="accent" :icon="$category->icon ?: 'bi-tag'">{{ $category->path_label }}</x-ui.catalog-chip>
                                <small>{{ $category->expenses_count ? $category->expenses_count.' '.($category->expenses_count === 1 ? 'gasto' : 'gastos') : 'Sin gastos' }}</small>
                            </div>
                            <x-ui.icon-action icon="bi-pencil" label="Renombrar {{ $category->name }}" size="sm" wire:click="startCategoryRename('{{ $category->id }}')" />
                            <x-ui.icon-action icon="bi-trash" tone="danger" label="Eliminar {{ $category->name }}" size="sm" wire:click="deleteCategory('{{ $category->id }}')" />
                        @endif
                    </li>
                @endforeach
            </ul>
        @else
            <x-ui.state variant="empty" icon="bi-tags" title="No tienes categorías" message="Crea una arriba o recupera las predeterminadas." />
        @endif

        <x-ui.action variant="text" icon="bi-arrow-counterclockwise" wire:click="restoreDefaultCategories" class="align-self-start">Restaurar predeterminadas</x-ui.action>
    </div>
</x-ui.form-dialog>
