<x-module-shell module="meals">
    <x-slot:actions>
        <livewire:meal.bulk-ingredient-assistant context="ingredients" />
        <x-module-actions :primary="['label' => 'Agregar ingrediente', 'icon' => 'bi-basket', 'action' => 'openForm']"
                          :secondary="[['label' => 'Tiendas', 'icon' => 'bi-shop', 'event' => 'open-store-catalog']]" />
        <livewire:meal.store-catalog />
    </x-slot:actions>

    <x-ui.management-card id="meal-ingredients" title="Catálogo de ingredientes" icon="bi-basket" :count="'('.$ingredients->total().' / '.$catalogTotal.')'"
                          search="search" search-placeholder="Buscar ingredientes" :active-filters="$activeFilters"
                          :paginator="$ingredients" noun="ingredientes" alpine="openMenu: null">
        <x-slot:filters>
            <x-ui.multi-select name="categoryFilter" label="Categoría" :options="$this->categoryOptions" all-label="Todas las categorías" icon="bi-tag" />
            <x-ui.multi-select name="locationFilter" label="Ubicación" :options="$this->locationFilterOptions()" all-label="Todas las ubicaciones" icon="bi-geo-alt" />
            <x-ui.select name="filterStore" label="Tienda" placeholder="Todas" icon="bi-shop" :selected="$storeFilter" wire:model.live="storeFilter"
                         :options="['__none' => 'Sin tienda'] + $stores->all()" />
            <x-ui.select name="filterPrice" label="Precio" placeholder="Todos" icon="bi-currency-dollar" :selected="$priceFilter" wire:model.live="priceFilter"
                         :options="['with' => 'Con precio', 'without' => 'Sin precio']" />
            <x-ui.select name="filterCart" label="Lista de compras" placeholder="Todos" icon="bi-cart3" :selected="$cartFilter" wire:model.live="cartFilter"
                         :options="['yes' => 'En la lista', 'no' => 'Fuera de la lista']" />
            <x-ui.select name="filterUsage" label="Uso en recetas" placeholder="Todos" icon="bi-book" :selected="$usageFilter" wire:model.live="usageFilter"
                         :options="['used' => 'Usados en recetas', 'unused' => 'Sin usar en recetas']" />
            <x-ui.select name="filterData" label="Datos" placeholder="Todos" icon="bi-exclamation-diamond" :selected="$dataFilter" wire:model.live="dataFilter"
                         :options="['pending' => 'Pendientes de completar']" />
        </x-slot:filters>
        <x-slot:filterActions>
            <x-ui.action variant="outlined" icon="bi-eraser" wire:click="clearFilters" x-on:click="filtersOpen = false">Limpiar</x-ui.action>
            <x-ui.action variant="filled" icon="bi-funnel-fill" x-on:click="filtersOpen = false">Filtrar</x-ui.action>
        </x-slot:filterActions>
        @if ($appliedFilters !== [])
            <x-slot:strip>
                <x-ui.applied-filters :filters="$appliedFilters" />
            </x-slot:strip>
        @endif

    @if ($ingredients->isEmpty())
        <div class="md-card-elevated p-4 text-center">
            <i class="bi bi-basket" style="font-size: 2rem; color: var(--md-sys-color-outline);"></i>
            <p class="md-body-large mt-2" style="color: var(--md-sys-color-on-surface-variant);">
                {{ $search || $activeFilters ? 'No se encontraron ingredientes con esos filtros.' : 'Aún no tienes ingredientes. ¡Agrega el primero!' }}
            </p>
        </div>
    @else
        <table class="md-table md-table--stack">
            <thead>
                <tr>
                    <th scope="col">Ingrediente</th>
                    <th scope="col">Categoría</th>
                    <th scope="col">Stock</th>
                    <th scope="col">Estado</th>
                    <th scope="col" class="md-table__actions"><span class="visually-hidden">Acciones</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($ingredients as $item)
                    @php
                        [$statusLabel, $statusTone] = match (true) {
                            (float) $item->stock <= 0 => ['Sin stock', 'danger'],
                            $item->isBelowMinimum() => ['Por agotarse', 'warning'],
                            default => ['En stock', 'success'],
                        };
                    @endphp
                    <tr wire:key="ingredient-{{ $item->id }}">
                        <td class="md-table__title">
                            {{ $item->name }}
                            @if ($item->next_purchase)<i class="bi bi-cart-check-fill ingredient-in-cart" title="En la lista de compras" aria-label="En la lista de compras"></i>@endif
                            @unless ($item->base_unit)<span class="md-chip md-chip--small ingredient-flag" title="Define la unidad base: g, ml o unidad">Sin unidad base</span>@endunless
                            @if ($item->variants->isNotEmpty())
                                <span class="md-table__meta ingredient-offers">
                                    @foreach ($item->variants as $variant)
                                        @php
                                            $cheapest = $variant->latestPrices()->sortBy(fn ($p) => (float) $p->amount)->first();
                                            $perBase = $variant->pricePerBase($cheapest, $item->base_unit);
                                        @endphp
                                        <span class="ingredient-variant-chip {{ $variant->isComparable() ? '' : 'is-pending' }}" title="{{ $variant->isComparable() ? '' : 'Pendiente de completar: falta el contenido' }}">
                                            @if ($variant->is_preferred)<i class="bi bi-star-fill" aria-label="Preferida"></i>@endif
                                            {{ $variant->label($item->base_unit) }}
                                            @if ($cheapest) · {{ $cheapest->store?->name }} ${{ number_format($cheapest->amount, 0, ',', '.') }}@endif
                                            @if ($perBase !== null) <small>(${{ number_format($perBase, 0, ',', '.') }}/{{ \App\Services\Meal\UnitConverter::comparisonLabel($item->base_unit) }})</small>@endif
                                        </span>
                                    @endforeach
                                </span>
                            @endif
                        </td>
                        <td>{{ $item->category?->name ?? 'Sin categoría' }}</td>
                        <td class="md-table__nowrap {{ $item->isBelowMinimum() ? 'ingredient-flag' : '' }}">{{ (float) $item->stock > 0 ? $item->formatQuantity($item->stock) : '—' }}</td>
                        <td><x-ui.badge :tone="$statusTone">{{ $statusLabel }}</x-ui.badge></td>
                        <td class="md-table__actions">
                            <x-ui.row-actions :label="'Más acciones de '.$item->name">
                                <x-slot:primary wire:click="openForm('{{ $item->id }}')">Editar</x-slot:primary>
                                @if ($item->variants->isNotEmpty())
                                    <x-ui.menu-item icon="bi-bar-chart-steps" :href="route('meals.compare', $item)" wire:navigate>Comparar precios</x-ui.menu-item>
                                @endif
                                <x-ui.menu-item :icon="$item->next_purchase ? 'bi-cart-dash' : 'bi-cart-plus'" wire:click="toggleNextPurchase('{{ $item->id }}')">{{ $item->next_purchase ? 'Quitar de compras' : 'Agregar a compras' }}</x-ui.menu-item>
                                <x-ui.menu-item icon="bi-intersect" wire:click="openMerge('{{ $item->id }}')">Convertir en variante de…</x-ui.menu-item>
                                <x-ui.menu-divider />
                                <x-ui.menu-item icon="bi-trash" tone="danger" wire:click="delete('{{ $item->id }}')"
                                                wire:confirm="El ingrediente y sus variantes, precios y alias se eliminan de forma permanente.">Eliminar</x-ui.menu-item>
                            </x-ui.row-actions>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
    </x-ui.management-card>

    @if ($merge)
        @php
            $p = $merge['preview'];
            $plural = fn (int $n, string $one, string $many) => $n.' '.($n === 1 ? $one : $many);
            $flavorTag = trim($mergeFlavor);
        @endphp
        <div x-data="{ open: true }" wire:key="merge-{{ $merge['source']->id }}">
            <x-ui.dialog state="open" title="Convertir en variante" icon="bi-intersect" size="lg" x-on:md-surface-close="$wire.closeMerge()">
                <div class="ingredient-merge">
                    <p class="md-body-medium"><strong>{{ $merge['source']->name }}</strong> dejará de ser un producto aparte: pasará a ser una variante del producto principal que elijas.</p>

                    <section class="ingredient-merge__step">
                        <h3 class="md-title-small"><span class="ingredient-merge__num">1</span> Producto principal</h3>
                        <x-ui.combobox name="mergeTargetId" label="Convertir en variante de" placeholder="Sin elegir" :options="$merge['targets']" wire:model.live="mergeTargetId" />
                        @if ($mergeSuggested)
                            <p class="md-supporting-text"><i class="bi bi-lightbulb" aria-hidden="true"></i> Sugerido por el nombre.</p>
                        @endif
                    </section>

                    <section class="ingredient-merge__step">
                        <h3 class="md-title-small"><span class="ingredient-merge__num">2</span> Cómo queda la variante</h3>
                        <p class="md-supporting-text">Lo que diferencia a este producto del principal. Se aplica a cada una de sus variantes.</p>
                        <x-ui.field name="mergeFlavor" label="Sabor o tipo" maxlength="60" help="Ej. manzanilla, congelada, infantil." wire:model.live.debounce.300ms="mergeFlavor" />
                        @if ($merge['variants']->isNotEmpty())
                            <ul class="ingredient-merge__variants">
                                @foreach ($merge['variants'] as $variant)
                                    @php
                                        $after = collect([$variant->brand?->name, collect([$flavorTag ?: null, $variant->flavor])->filter()->unique()->implode(' · ') ?: null, \App\Models\ShoppingItemVariant::PACKAGINGS[$variant->packaging] ?? null])->filter()->implode(' · ');
                                    @endphp
                                    <li wire:key="merge-variant-{{ $variant->id }}"><span>{{ $variant->label() }}</span><span><i class="bi bi-arrow-right" aria-hidden="true"></i> {{ $after ?: 'Variante' }}</span></li>
                                @endforeach
                            </ul>
                        @elseif ($flavorTag !== '')
                            <p class="md-supporting-text">No tiene variantes: se creará la variante «{{ $flavorTag }}» en el producto principal.</p>
                        @endif
                    </section>

                    <section class="ingredient-merge__step">
                        <h3 class="md-title-small"><span class="ingredient-merge__num">3</span> Qué se mueve</h3>
                        <ul class="ingredient-merge__moves">
                            <li><i class="bi bi-tag" aria-hidden="true"></i><span><strong>{{ $plural($p['prices'], 'precio', 'precios') }}</strong> {{ $p['prices'] === 1 ? 'pasa' : 'pasan' }} a la variante, con su tienda y fecha.</span></li>
                            <li><i class="bi bi-box-seam" aria-hidden="true"></i><span><strong>Stock:</strong> {{ $p['stock'] > 0 ? $merge['source']->formatQuantity($p['stock']) : 'nada' }} se suma al producto principal.</span></li>
                            <li><i class="bi bi-receipt" aria-hidden="true"></i><span><strong>{{ $plural($p['purchases'], 'línea de compra', 'líneas de compra') }}</strong> {{ $p['purchases'] === 1 ? 'queda registrada' : 'quedan registradas' }} con el producto principal.</span></li>
                            <li><i class="bi bi-book" aria-hidden="true"></i><span><strong>{{ $plural($p['recipes'], 'receta', 'recetas') }}</strong> {{ $p['recipes'] === 1 ? 'usará' : 'usarán' }} el producto principal.</span></li>
                            @if ($merge['source']->next_purchase)
                                <li><i class="bi bi-cart3" aria-hidden="true"></i><span>Está en la lista de compras: el principal queda en la lista.</span></li>
                            @endif
                        </ul>
                        <label class="d-flex align-items-center gap-2"><input type="checkbox" class="md-checkbox" wire:model="mergeKeepAlias"> <span class="md-body-medium">Guardar «{{ $merge['source']->name }}» como alias del principal</span></label>
                        <p class="ingredient-merge__warning" role="note"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> «{{ $merge['source']->name }}» se elimina del catálogo. Esta acción no se puede deshacer.</p>
                    </section>
                </div>
                <x-slot:actions>
                    <x-ui.action variant="text" wire:click="closeMerge">Cancelar</x-ui.action>
                    <x-ui.action variant="filled" icon="bi-intersect" wire:click="confirmMerge" wire:loading.attr="disabled" wire:target="confirmMerge">Convertir</x-ui.action>
                </x-slot:actions>
            </x-ui.dialog>
        </div>
    @endif


    <x-ui.form-dialog :open="$showForm" close="closeForm" submit-action="save" id="ingredient-dialog"
                      :title="$editingId ? 'Editar ingrediente' : 'Agregar ingrediente'" icon="bi-basket"
                      :submit="$editingId ? 'Actualizar' : 'Guardar'"
                      :sections="[
                          'basic' => ['label' => 'Información básica', 'icon' => 'bi-basket', 'error' => $errors->hasAny(['name', 'baseUnit', 'gramsPerPiece'])],
                          'inventory' => ['label' => 'Inventario', 'icon' => 'bi-box-seam', 'error' => $errors->hasAny(['stock', 'minStock', 'toBuy'])],
                          'nutrition' => ['label' => 'Nutrición', 'icon' => 'bi-fire', 'error' => $errors->hasAny(['kcal', 'protein', 'carbs', 'fat'])],
                          'variants' => ['label' => 'Variantes', 'icon' => 'bi-box', 'error' => $errors->has('variants') || $errors->has('variants.*')],
                          'prices' => ['label' => 'Precios por tienda', 'icon' => 'bi-shop', 'error' => $errors->has('prices.*')],
                          'aliases' => ['label' => 'Alias y equivalencias', 'icon' => 'bi-tags', 'error' => $errors->has('aliases') || $errors->has('aliases.*')],
                      ]">
        @php
            $unitShort = ['g' => 'g', 'ml' => 'ml', 'unit' => 'und'][$baseUnit] ?? '';
            $nutritionBasis = ['g' => 'por 100 g', 'ml' => 'por 100 ml', 'unit' => 'por unidad'][$baseUnit] ?? '';
        @endphp
        <x-ui.form-dialog-section name="basic" title="Información básica" description="El nombre es el producto genérico, sin marca ni tamaño: «Aceite vegetal», no «Aceite Imatá 900 ml».">
            <div class="d-flex flex-column gap-3">
                <x-ui.field name="name" label="Nombre" :required="true" wire:model.live.debounce.400ms="name" />
                @if ($nameSuggestions->isNotEmpty())
                    <p class="md-supporting-text ingredient-similar" role="status">
                        <i class="bi bi-info-circle" aria-hidden="true"></i> Ya tienes productos parecidos: {{ $nameSuggestions->implode(', ') }}.
                    </p>
                @endif
                <div class="md-field-pair">
                    <x-ui.combobox name="categoryId" label="Categoría" placeholder="Sin categoría" :options="$this->categoryOptions" wire:model="categoryId" />
                    <x-ui.select name="baseUnit" label="Unidad base" placeholder="Elige g, ml o unidad" :required="true"
                                 :options="\App\Models\ShoppingItem::BASE_UNITS" :selected="$baseUnit" :disabled="$baseUnitLocked"
                                 :help="$baseUnitLocked ? 'La unidad base no cambia una vez definida.' : 'Todo lo de este producto se guarda en esta unidad.'"
                                 wire:model.live="baseUnit" />
                </div>
                <x-ui.category-select name="storageLocationId" label="Ubicación habitual" placeholder="Sin ubicación" icon="bi-geo-alt" :options="$this->storageLocationOptions()" :selected="$storageLocationId" wire:model="storageLocationId" />
                @if ($baseUnit === 'g')
                    <x-ui.field name="gramsPerPiece" label="Gramos por pieza (opcional)" type="number" step="0.001" min="0"
                                help="Para productos que se compran por peso pero se cuentan por pieza, como el aguacate." wire:model="gramsPerPiece" />
                @endif
            </div>
        </x-ui.form-dialog-section>

        <x-ui.form-dialog-section name="inventory" title="Inventario" :description="$unitShort ? 'Cantidades en '.$unitShort.'.' : 'Elige primero la unidad base.'">
            <div class="d-flex flex-column gap-3">
                <div class="md-field-pair">
                    <x-ui.field name="stock" label="Stock en casa" type="number" step="0.001" min="0" wire:model="stock" />
                    <x-ui.field name="minStock" label="Stock mínimo" type="number" step="0.001" min="0" wire:model="minStock" />
                </div>
                @if ($purchaseHistory)
                    <div class="md-body-medium">
                        @if ($purchaseHistory['last_purchase'])
                            <p>Última compra: {{ \Carbon\Carbon::parse($purchaseHistory['last_purchase']['purchased_at'])->format('d/m/Y H:i') }} · {{ $purchaseHistory['last_purchase']['store'] ?? 'Sin tienda' }} · Precio por paquete: {{ $purchaseHistory['last_purchase']['unit_price'] === null ? 'Sin precio' : '$'.number_format((float) $purchaseHistory['last_purchase']['unit_price'], 2, ',', '.') }}</p>
                        @else
                            <p>Sin compras registradas.</p>
                        @endif
                        <p>Compras en 30 / 60 / 90 días: {{ implode(' / ', $purchaseHistory['counts']) }}</p>
                    </div>
                @endif
                <div class="md-field-pair">
                    <x-ui.field name="toBuy" label="Por comprar (paquetes)" type="number" step="0.001" min="0" wire:model="toBuy" />
                    <x-ui.field name="consumeBy" label="Consumir antes" type="date" wire:model="consumeBy" />
                </div>
                <x-ui.switch name="nextPurchase" label="Incluir en lista de compras" wire:model="nextPurchase" />
            </div>
        </x-ui.form-dialog-section>

        <x-ui.form-dialog-section name="nutrition" title="Nutrición" :description="$nutritionBasis ? 'Valores '.$nutritionBasis.'. Las recetas los usan para calcular sus calorías.' : 'Elige primero la unidad base.'">
            <div class="d-flex flex-column gap-3">
                <div class="md-field-pair">
                    <x-ui.field name="kcal" label="Calorías (kcal)" type="number" step="0.01" min="0" wire:model="kcal" />
                    <x-ui.field name="protein" label="Proteína (g)" type="number" step="0.01" min="0" wire:model="protein" />
                </div>
                <div class="md-field-pair">
                    <x-ui.field name="carbs" label="Carbohidratos (g)" type="number" step="0.01" min="0" wire:model="carbs" />
                    <x-ui.field name="fat" label="Grasa (g)" type="number" step="0.01" min="0" wire:model="fat" />
                </div>
            </div>
        </x-ui.form-dialog-section>

        <x-ui.form-dialog-section name="variants" title="Variantes" description="Cada variante es una marca, empaque y contenido concretos.">
            <datalist id="ingredient-brands-list">
                @foreach ($brands as $brandName)<option value="{{ $brandName }}">@endforeach
            </datalist>
            @error('variants')<p class="md-supporting-text" role="alert">{{ $message }}</p>@enderror
            <div class="ingredient-rows">
                @forelse ($variants as $index => $variant)
                    <article class="ingredient-row" wire:key="variant-{{ $variant['key'] }}">
                        <div class="ingredient-row__fields ingredient-row__fields--variant">
                            <x-ui.field name="variants.{{ $index }}.brand" label="Marca" id="var-brand-{{ $index }}" list="ingredient-brands-list" autocomplete="off" wire:model="variants.{{ $index }}.brand" />
                            <x-ui.field name="variants.{{ $index }}.flavor" label="Sabor" id="var-flavor-{{ $index }}" maxlength="60" wire:model="variants.{{ $index }}.flavor" />
                            <x-ui.select name="variants.{{ $index }}.packaging" label="Empaque" id="var-pack-{{ $index }}" placeholder="Sin definir"
                                         :options="\App\Models\ShoppingItemVariant::PACKAGINGS" :selected="$variant['packaging']" wire:model="variants.{{ $index }}.packaging" />
                            <x-ui.field name="variants.{{ $index }}.content" label="Contenido" type="number" step="0.001" min="0" id="var-content-{{ $index }}" wire:model="variants.{{ $index }}.content" />
                            <x-ui.select name="variants.{{ $index }}.content_unit" label="Unidad" id="var-content-unit-{{ $index }}"
                                         :options="\App\Livewire\Meal\MealIngredients::CONTENT_UNITS[$baseUnit] ?? []" :selected="$variant['content_unit']" :disabled="$baseUnit === ''"
                                         wire:model="variants.{{ $index }}.content_unit" />
                        </div>
                        <button wire:click="removeVariant({{ $index }})" type="button" class="md-btn-icon md-btn-icon--small md-btn-danger ingredient-row__remove" aria-label="Quitar variante {{ $index + 1 }}"><i class="bi bi-trash" aria-hidden="true"></i></button>
                        <x-ui.inline-fields class="ingredient-row__meta" :open="$errors->hasAny(['variants.'.$index.'.units_per_pack', 'variants.'.$index.'.barcode', 'variants.'.$index.'.kcal'])"
                                            :summary="['Unidades: '.($variant['units_per_pack'] ?: 'Sin definir'), 'Kcal: '.(filled($variant['kcal']) ? $variant['kcal'] : 'Sin definir'), 'Código: '.($variant['barcode'] ?: 'Sin definir')]">
                            <x-ui.field name="variants.{{ $index }}.units_per_pack" label="Unidades por paquete" type="number" min="1" id="var-units-{{ $index }}" wire:model.blur="variants.{{ $index }}.units_per_pack" />
                            <x-ui.field name="variants.{{ $index }}.kcal" label="Kcal propias ({{ $nutritionBasis ?: 'por unidad base' }})" type="number" step="0.01" min="0" id="var-kcal-{{ $index }}" wire:model.blur="variants.{{ $index }}.kcal" />
                            <x-ui.field name="variants.{{ $index }}.barcode" label="Código de barras" id="var-barcode-{{ $index }}" wire:model.blur="variants.{{ $index }}.barcode" />
                            <x-slot:aside><label class="ingredient-row__check"><input type="checkbox" class="md-checkbox" wire:model="variants.{{ $index }}.is_preferred"> Preferida</label></x-slot:aside>
                        </x-ui.inline-fields>
                    </article>
                @empty
                    <div class="md-form-empty"><i class="bi bi-box" aria-hidden="true"></i><p>No hay variantes registradas. Agrega una para guardar marca, contenido y precios.</p></div>
                @endforelse
            </div>
            <button wire:click="addVariant" type="button" class="md-btn-tonal mt-3" @disabled($baseUnit === '')><i class="bi bi-plus-lg" aria-hidden="true"></i> Agregar variante</button>
        </x-ui.form-dialog-section>

        @php
            $variantOptions = collect($variants)->mapWithKeys(fn ($variant, $index) => [$variant['key'] => collect([
                $variant['brand'] ?: null,
                $variant['flavor'] ?: null,
                filled($variant['content']) ? $variant['content'].' '.$variant['content_unit'] : null,
                \App\Models\ShoppingItemVariant::PACKAGINGS[$variant['packaging']] ?? null,
            ])->filter()->implode(' · ') ?: 'Variante '.($index + 1)])->all();
        @endphp
        <x-ui.form-dialog-section name="prices" title="Precios por tienda" description="Cada precio lleva su variante, tienda, fecha y fuente.">
            <div class="ingredient-rows">
                @forelse ($prices as $priceIndex => $price)
                    @if (! empty($price['purchase_line_id']))
                        <p class="md-body-small ingredient-ticket-price" wire:key="price-{{ $price['id'] }}"><i class="bi bi-receipt" aria-hidden="true"></i> Ticket: ${{ number_format((float) $price['amount'], 0, ',', '.') }} · {{ $variantOptions[$price['variant_key']] ?? '' }} · {{ $stores[$price['store_id']] ?? 'Sin tienda' }} · {{ \Carbon\Carbon::parse($price['observed_on'])->format('d/m/Y') }}. Se corrige desde el historial de compras.</p>
                        @continue
                    @endif
                    <article class="ingredient-row" wire:key="price-{{ $price['id'] ?? 'new-'.$priceIndex }}">
                        <div class="ingredient-row__fields ingredient-row__fields--price">
                            <x-ui.select name="prices.{{ $priceIndex }}.variant_key" label="Variante" id="price-variant-{{ $priceIndex }}" placeholder="Elige la variante"
                                         :options="$variantOptions" :selected="$price['variant_key']" wire:model="prices.{{ $priceIndex }}.variant_key" />
                            <x-ui.select name="prices.{{ $priceIndex }}.store_id" label="Tienda" id="price-store-{{ $priceIndex }}" placeholder="Elige la tienda"
                                         :options="$stores->all()" :selected="$price['store_id']" wire:model="prices.{{ $priceIndex }}.store_id" />
                            {{-- Muestra el precio con separador de miles mientras se escribe; el componente quita los puntos al guardar. --}}
                            <x-ui.field name="prices.{{ $priceIndex }}.amount" label="Precio" inputmode="numeric" autocomplete="off" id="price-amount-{{ $priceIndex }}"
                                        x-data="{
                                            format(input) {
                                                const after = input.value.slice(input.selectionStart).replace(/\D/g, '').length;
                                                const digits = input.value.replace(/\D/g, '');
                                                input.value = digits ? Number(digits).toLocaleString('es-CO') : '';
                                                let caret = input.value.length;
                                                for (let seen = 0; caret > 0 && seen < after; caret--) if (/\d/.test(input.value[caret - 1])) seen++;
                                                input.setSelectionRange(caret, caret);
                                            },
                                        }"
                                        x-init="$nextTick(() => format($el))" x-on:input="format($el)"
                                        wire:model="prices.{{ $priceIndex }}.amount" />
                        </div>
                        <button wire:click="removePrice({{ $priceIndex }})" type="button" class="md-btn-icon md-btn-icon--small md-btn-danger ingredient-row__remove" aria-label="Quitar precio {{ $priceIndex + 1 }}"><i class="bi bi-trash" aria-hidden="true"></i></button>
                        <x-ui.inline-fields class="ingredient-row__meta" :open="$errors->hasAny(['prices.'.$priceIndex.'.observed_on', 'prices.'.$priceIndex.'.source'])"
                                            :summary="['Fuente: '.(\App\Models\ShoppingItemPrice::SOURCES[$price['source']] ?? 'Sin definir'), 'Fecha: '.(filled($price['observed_on']) ? \Carbon\Carbon::parse($price['observed_on'])->format('d/m/Y') : 'Sin definir')]">
                            <x-ui.select name="prices.{{ $priceIndex }}.source" label="Fuente" id="price-source-{{ $priceIndex }}"
                                         :options="\App\Models\ShoppingItemPrice::SOURCES" :selected="$price['source']" wire:model.live="prices.{{ $priceIndex }}.source" />
                            <x-ui.field name="prices.{{ $priceIndex }}.observed_on" label="Fecha" type="date" id="price-date-{{ $priceIndex }}" wire:model.blur="prices.{{ $priceIndex }}.observed_on" />
                            <x-slot:aside><label class="ingredient-row__check"><input type="checkbox" class="md-checkbox" wire:model="prices.{{ $priceIndex }}.verified"> Verificado</label></x-slot:aside>
                        </x-ui.inline-fields>
                    </article>
                @empty
                    <div class="md-form-empty"><i class="bi bi-shop-window" aria-hidden="true"></i><p>Sin precios registrados.</p></div>
                @endforelse
            </div>
            @if ($variants === [])
                <p class="md-supporting-text mt-3">Agrega una variante antes de registrar precios.</p>
            @endif
            <div class="d-flex flex-wrap gap-2 mt-3">
                <button wire:click="addPrice" type="button" class="md-btn-tonal" @disabled($variants === [] || $stores->isEmpty())><i class="bi bi-plus-lg" aria-hidden="true"></i> Agregar precio</button>
                <button type="button" class="md-btn-text" x-on:click="$dispatch('open-store-catalog')"><i class="bi bi-shop" aria-hidden="true"></i> {{ $stores->isEmpty() ? 'Crea primero una tienda' : 'Gestionar tiendas' }}</button>
            </div>
        </x-ui.form-dialog-section>

        <x-ui.form-dialog-section name="aliases" title="Alias y equivalencias" description="Nombres alternativos que reconocerá el asistente, por ejemplo «arroz» para «Arroz blanco».">
            <div class="ingredient-rows">
                @forelse ($aliases as $index => $alias)
                    <div class="ingredient-row" wire:key="alias-{{ $alias['id'] ?? 'new-'.$index }}">
                        <div class="ingredient-row__fields">
                            <x-ui.field name="aliases.{{ $index }}.alias" label="Alias {{ $index + 1 }}" id="ingredient-alias-{{ $index }}" wire:model="aliases.{{ $index }}.alias" />
                        </div>
                        <button wire:click="removeAlias({{ $index }})" type="button" class="md-btn-icon md-btn-icon--small md-btn-danger ingredient-row__remove" aria-label="Quitar alias {{ $index + 1 }}"><i class="bi bi-trash" aria-hidden="true"></i></button>
                    </div>
                @empty
                    <div class="md-form-empty" style="min-height: 96px;"><i class="bi bi-tags" aria-hidden="true"></i><p>Este ingrediente aún no tiene nombres alternativos.</p></div>
                @endforelse
            </div>
            @error('aliases')<p class="md-supporting-text" role="alert">{{ $message }}</p>@enderror
            <button wire:click="addAlias" type="button" class="md-btn-tonal mt-3"><i class="bi bi-plus-lg" aria-hidden="true"></i> Agregar alias</button>

            @if ($editingId)
                <div class="mt-4">
                    <x-ui.destructive-action label="Eliminar ingrediente" action="delete('{{ $editingId }}')"
                                             title="Eliminar ingrediente" message="El ingrediente y sus variantes se eliminan de forma permanente." />
                </div>
            @endif
        </x-ui.form-dialog-section>
    </x-ui.form-dialog>
</x-module-shell>
