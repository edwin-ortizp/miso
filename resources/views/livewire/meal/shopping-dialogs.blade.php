    <x-ui.form-dialog :open="$showForm" close="closeForm" submit-action="save" id="shopping-item-dialog"
                      title="Agregar a compras" icon="bi-cart-plus"
                      :sections="[
                          'basic' => ['label' => 'Información básica', 'icon' => 'bi-cart3', 'error' => $errors->hasAny(['itemName', 'itemQuantity', 'itemBaseUnit'])],
                          'store' => ['label' => 'Tienda y precio', 'icon' => 'bi-shop', 'error' => $errors->hasAny(['itemStoreId', 'itemPrice'])],
                      ]">
        <x-ui.form-dialog-section name="basic" title="Información básica" description="Si el ítem ya existe en tus ingredientes se reutiliza.">
            <div class="d-flex flex-column gap-3">
                <x-ui.field name="itemName" label="Nombre" :required="true" list="shopping-catalog-list" autocomplete="off" wire:model="itemName" />
                <datalist id="shopping-catalog-list">
                    @foreach ($catalogNames as $catalogName)
                        <option value="{{ $catalogName }}">
                    @endforeach
                </datalist>
                <div class="md-field-pair">
                    <x-ui.field name="itemQuantity" label="Cantidad (paquetes)" type="number" step="0.001" min="0" wire:model="itemQuantity" />
                    <x-ui.select name="itemBaseUnit" label="Unidad base" placeholder="g, ml o unidad" :options="\App\Models\ShoppingItem::BASE_UNITS"
                                 :selected="$itemBaseUnit" help="Solo para productos nuevos." wire:model="itemBaseUnit" />
                </div>
                <x-ui.category-select name="itemCategory" label="Categoría" placeholder="Sin categoría" :options="$this->categoryOptions" :selected="$itemCategory" wire:model="itemCategory" />
            </div>
        </x-ui.form-dialog-section>

        <x-ui.form-dialog-section name="store" title="Tienda y precio" description="Opcional: guarda dónde lo consigues y a qué precio.">
            <div class="md-field-pair">
                <x-ui.select name="itemStoreId" label="Tienda" placeholder="Elige la tienda" :options="$places->all()" :selected="$itemStoreId" wire:model="itemStoreId" />
                <x-ui.field name="itemPrice" label="Precio" type="number" step="0.01" min="0" wire:model="itemPrice" />
            </div>
            <button type="button" class="md-btn-text mt-2" x-on:click="$dispatch('open-store-catalog')"><i class="bi bi-shop" aria-hidden="true"></i> Gestionar tiendas</button>
        </x-ui.form-dialog-section>
    </x-ui.form-dialog>

    @if ($purchaseItem)
        <x-ui.form-dialog :open="$showPurchase" close="closePurchase" submit-action="confirmPurchase" id="shopping-purchase-dialog"
                          :title="'Comprado: '.$purchaseItem->name" icon="bi-bag-check" submit="Marcar comprado">
            <div class="d-flex flex-column gap-3">
                <p class="md-body-medium mb-0 meal-muted">
                    Se suma al stock en casa y sale de la lista. El precio pagado queda en el historial como precio de ticket verificado.
                </p>
                @if ($purchaseItem->variants->isNotEmpty())
                    <x-ui.select name="purchaseVariantId" label="Variante comprada" placeholder="Sin especificar"
                                 :options="$purchaseItem->variants->mapWithKeys(fn ($v) => [$v->id => $v->label($purchaseItem->base_unit)])->all()"
                                 :selected="$purchaseVariantId" wire:model="purchaseVariantId" />
                @endif
                <div class="md-field-pair">
                    <x-ui.field name="purchaseQuantity" label="Paquetes comprados" type="number" step="0.001" min="0" wire:model="purchaseQuantity" />
                    <x-ui.field name="purchaseAmount" label="Precio pagado por paquete" type="number" step="0.01" min="0" wire:model="purchaseAmount" />
                </div>
                <x-ui.select name="purchaseStoreId" label="Tienda" placeholder="Elige la tienda" :options="$places->all()" :selected="$purchaseStoreId" wire:model="purchaseStoreId" />
            </div>
        </x-ui.form-dialog>
    @endif
