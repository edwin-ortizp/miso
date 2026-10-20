<x-module-shell module="meals">
    <x-slot:actions>
        <x-module-actions :primary="['label' => 'Crear receta', 'icon' => 'bi-book', 'action' => 'openForm']" />
    </x-slot:actions>

    @php $recipeFilterCount = collect([$favoriteFilter, $mealTypeFilter, $difficultyFilter])->filter()->count(); @endphp
    <x-ui.management-card id="meal-recipes" title="Recetas" icon="bi-book" :count="'('.$recipes->total().')'"
                          search="search" search-placeholder="Buscar recetas" :active-filters="$recipeFilterCount"
                          :paginator="$recipes" noun="recetas" alpine="openMenu: null">
        <x-slot:filters>
            <div class="md-chip-rail md-mcard__filter-chips" role="group" aria-label="Filtros de recetas" @click.outside="openMenu = null">
        
            <button wire:click="$toggle('favoriteFilter')"
                    class="md-chip md-chip-filter {{ $favoriteFilter ? 'selected' : '' }}">
                <i class="bi bi-heart{{ $favoriteFilter ? '-fill' : '' }}"></i> Favoritos
            </button>

            <div class="md-chip-rail__divider"></div>

            {{-- Meal type chip-menu --}}
            <div class="md-chip-menu" :class="{ 'open': openMenu === 'mealType' }">
                <button @click="openMenu = openMenu === 'mealType' ? null : 'mealType'"
                        class="md-chip md-chip-filter {{ $mealTypeFilter ? 'selected' : '' }}">
                    {{ $mealTypeFilter ? $this->mealTypes[$mealTypeFilter] : 'Tipo de comida' }}
                    <i class="bi bi-chevron-down md-chip-menu__arrow"></i>
                </button>
                <div x-show="openMenu === 'mealType'" x-transition x-cloak class="md-chip-menu__dropdown">
                    <button wire:click="$set('mealTypeFilter', '')" @click="openMenu = null"
                            class="md-chip-menu__item {{ $mealTypeFilter === '' ? 'active' : '' }}">Todos</button>
                    @foreach ($this->mealTypes as $key => $label)
                        <button wire:click="$set('mealTypeFilter', '{{ $key }}')" @click="openMenu = null"
                                class="md-chip-menu__item {{ $mealTypeFilter === $key ? 'active' : '' }}">{{ $label }}</button>
                    @endforeach
                </div>
            </div>

            {{-- Difficulty chip-menu --}}
            <div class="md-chip-menu" :class="{ 'open': openMenu === 'difficulty' }">
                <button @click="openMenu = openMenu === 'difficulty' ? null : 'difficulty'"
                        class="md-chip md-chip-filter {{ $difficultyFilter ? 'selected' : '' }}">
                    {{ $difficultyFilter ? $this->difficulties[$difficultyFilter] : 'Dificultad' }}
                    <i class="bi bi-chevron-down md-chip-menu__arrow"></i>
                </button>
                <div x-show="openMenu === 'difficulty'" x-transition x-cloak class="md-chip-menu__dropdown">
                    <button wire:click="$set('difficultyFilter', '')" @click="openMenu = null"
                            class="md-chip-menu__item {{ $difficultyFilter === '' ? 'active' : '' }}">Todas</button>
                    @foreach ($this->difficulties as $key => $label)
                        <button wire:click="$set('difficultyFilter', '{{ $key }}')" @click="openMenu = null"
                                class="md-chip-menu__item {{ $difficultyFilter === $key ? 'active' : '' }}">{{ $label }}</button>
                    @endforeach
                </div>
            </div>
            </div>
        </x-slot:filters>
        @php
            $appliedChips = array_values(array_filter([
                $favoriteFilter ? ['key' => 'favoriteFilter', 'value' => null, 'label' => 'Favoritos', 'icon' => 'bi-heart-fill'] : null,
                $mealTypeFilter !== '' ? ['key' => 'mealTypeFilter', 'value' => null, 'label' => 'Tipo: '.($this->mealTypes[$mealTypeFilter] ?? $mealTypeFilter), 'icon' => 'bi-egg-fried'] : null,
                $difficultyFilter !== '' ? ['key' => 'difficultyFilter', 'value' => null, 'label' => 'Dificultad: '.($this->difficulties[$difficultyFilter] ?? $difficultyFilter), 'icon' => 'bi-speedometer2'] : null,
            ]));
        @endphp
        @if ($appliedChips !== [])
            <x-slot:strip>
                <x-ui.applied-filters :filters="$appliedChips" />
            </x-slot:strip>
        @endif
    @if ($recipes->isEmpty())
        <div class="md-card-elevated p-4 text-center">
            <i class="bi bi-book" style="font-size: 2rem; color: var(--md-sys-color-outline);"></i>
            <p class="md-body-large mt-2" style="color: var(--md-sys-color-on-surface-variant);">
                {{ $search || $mealTypeFilter || $difficultyFilter || $favoriteFilter ? 'No se encontraron recetas con esos filtros.' : 'Aún no tienes recetas. ¡Agrega la primera!' }}
            </p>
        </div>
    @else
        <table class="md-table md-table--stack">
            <thead>
                <tr>
                    <th scope="col">Receta</th>
                    <th scope="col">Tipo</th>
                    <th scope="col">Dificultad</th>
                    <th scope="col">Tiempo</th>
                    <th scope="col">Ingredientes</th>
                    <th scope="col">Calorías</th>
                    <th scope="col">Costo / porción</th>
                    <th scope="col" class="md-table__actions"><span class="visually-hidden">Acciones</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($recipes as $recipe)
                    <tr wire:key="recipe-{{ $recipe->id }}">
                        <td class="md-table__title">
                            <button wire:click="toggleFavorite('{{ $recipe->id }}')" class="md-btn-icon md-btn-icon--small"
                                    title="{{ $recipe->favorite ? 'Quitar de favoritos' : 'Marcar como favorita' }}"
                                    aria-label="{{ $recipe->favorite ? 'Quitar de favoritos' : 'Marcar como favorita' }}">
                                <i class="bi bi-heart{{ $recipe->favorite ? '-fill' : '' }}" style="{{ $recipe->favorite ? 'color: var(--md-sys-color-error);' : '' }}"></i>
                            </button>
                            <a href="#" wire:click.prevent="openForm('{{ $recipe->id }}')" class="md-link">{{ $recipe->name }}</a>
                            @if ($recipe->description)<span class="md-table__meta">{{ \Illuminate\Support\Str::limit($recipe->description, 90) }}</span>@endif
                        </td>
                        <td><span class="md-chip md-chip--small">{{ $this->mealTypes[$recipe->meal_type] ?? $recipe->meal_type }}</span></td>
                        <td><span class="md-chip md-chip--small">{{ $this->difficulties[$recipe->difficulty] ?? $recipe->difficulty }}</span></td>
                        <td class="md-table__nowrap">{{ $recipe->prep_time ? $recipe->prep_time.' min' : '—' }}</td>
                        <td class="md-table__nowrap">{{ $recipe->recipe_ingredients_count }}</td>
                        <td class="md-table__nowrap">
                            {{ isset($recipe->nutrition['calories']) ? number_format((float) $recipe->nutrition['calories'], 0, ',', '.').' kcal' : '—' }}
                            @if (isset($recipe->nutrition['calories']))<span class="md-table__meta">{{ $recipe->nutrition_source === 'calculated' ? 'calculada' : 'manual' }}</span>@endif
                        </td>
                        <td class="md-table__nowrap">
                            @php $cost = $costs[$recipe->id] ?? null; @endphp
                            @if ($cost && $cost['cost_per_serving'] !== null)
                                ${{ number_format($cost['cost_per_serving'], 0, ',', '.') }}
                            @elseif ($cost && $cost['partial_cost'] > 0)
                                <span title="Faltan precios de: {{ implode(', ', $cost['missing_cost']) }}">≥ ${{ number_format($cost['partial_cost'] / max((float) $recipe->servings, 1), 0, ',', '.') }}</span>
                                <span class="md-table__meta">incompleto</span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="md-table__actions">
                            <x-ui.row-actions :label="'Más acciones de la receta '.$recipe->name">
                                <x-slot:primary wire:click="openForm('{{ $recipe->id }}')">Editar</x-slot:primary>
                                <x-ui.menu-item icon="bi-heart" wire:click="toggleFavorite('{{ $recipe->id }}')">{{ $recipe->favorite ? 'Quitar de favoritos' : 'Marcar como favorita' }}</x-ui.menu-item>
                                <x-ui.menu-divider />
                                <x-ui.menu-item icon="bi-trash" tone="danger" wire:click="delete('{{ $recipe->id }}')"
                                                wire:confirm="La receta se elimina de forma permanente.">Eliminar</x-ui.menu-item>
                            </x-ui.row-actions>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
    </x-ui.management-card>

    {{-- Dialog --}}
    <x-ui.form-dialog :open="$showForm" close="closeForm" submit-action="save" id="recipe-dialog"
                      :title="$editingId ? 'Editar receta' : 'Crear receta'" icon="bi-book"
                      :submit="$editingId ? 'Actualizar' : 'Guardar'"
                      :sections="[
                          'basic' => ['label' => 'Información básica', 'icon' => 'bi-card-heading', 'error' => $errors->has('name')],
                          'ingredients' => ['label' => 'Ingredientes', 'icon' => 'bi-basket', 'error' => $errors->has('ingredients.*')],
                          'preparation' => ['label' => 'Preparación', 'icon' => 'bi-list-ol'],
                          'nutrition' => ['label' => 'Costo y nutrición', 'icon' => 'bi-fire'],
                      ]">
        <x-ui.form-dialog-section name="basic" title="Información básica">
            <div class="d-flex flex-column gap-3">
                <x-ui.field name="name" label="Nombre de la receta" :required="true" wire:model="name" />
                <div class="md-field-trio">
                    <x-ui.select name="mealType" label="Tipo de comida" :options="$this->mealTypes" :selected="$mealType" wire:model="mealType" />
                    <x-ui.select name="difficulty" label="Dificultad" :options="$this->difficulties" :selected="$difficulty" wire:model="difficulty" />
                    <x-ui.field name="prepTime" label="Tiempo (min)" type="number" min="1" wire:model="prepTime" />
                </div>
                <x-ui.field name="servings" label="Porciones" type="number" step="0.5" min="0.5" :required="true"
                            help="Las cantidades de los ingredientes son para este número de porciones." wire:model="servings" />
                <x-ui.textarea name="description" label="Descripción (opcional)" rows="2" wire:model="description" />
                <label class="d-flex align-items-center gap-2" style="cursor: pointer;">
                    <input type="checkbox" wire:model="favorite" class="md-checkbox">
                    <span class="md-body-medium">Marcar como favorita</span>
                </label>
            </div>
        </x-ui.form-dialog-section>

        @php $productUnits = $shoppingItems->mapWithKeys(fn ($item) => [mb_strtolower($item->name) => $item->base_unit]); @endphp
        <x-ui.form-dialog-section name="ingredients" title="Ingredientes" description="Escribe la cantidad en g, ml o unidades (también kg, L, libras o piezas); se guarda en la unidad base del producto.">
            @foreach ($ingredients as $index => $ingredient)
                @php $ingredientBase = $productUnits[mb_strtolower(trim((string) ($ingredient['name'] ?? '')))] ?? null; @endphp
                <div class="row g-2 mb-2 align-items-start" wire:key="ingredient-{{ $index }}">
                    <div class="col-12 col-md-5">
                        <x-ui.field name="ingredients.{{ $index }}.name" label="Ingrediente" :required="true" id="ing-name-{{ $index }}" list="shopping-items-list" wire:model="ingredients.{{ $index }}.name" />
                    </div>
                    <div class="col-6 col-md-3">
                        <x-ui.field name="ingredients.{{ $index }}.quantity" label="Cantidad" type="number" :required="true" id="ing-qty-{{ $index }}" min="0.001" step="0.001" inputmode="decimal" wire:model="ingredients.{{ $index }}.quantity" />
                    </div>
                    <div class="col-5 col-md-3">
                        <x-ui.field name="ingredients.{{ $index }}.unit" label="Unidad" id="ing-unit-{{ $index }}" list="recipe-units-list"
                                    :help="$ingredientBase ? 'Base: '.(['g' => 'g', 'ml' => 'ml', 'unit' => 'unidades'][$ingredientBase] ?? $ingredientBase) : null"
                                    placeholder="{{ $ingredientBase === 'unit' ? 'unidades' : ($ingredientBase ?? 'g, ml, unidades') }}" wire:model="ingredients.{{ $index }}.unit" />
                    </div>
                    <div class="col-1 text-end">
                        <button wire:click="removeIngredient({{ $index }})" type="button" class="md-btn-icon md-btn-icon--small" aria-label="Quitar ingrediente">
                            <i class="bi bi-x-lg" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
            @endforeach

            <datalist id="recipe-units-list">
                @foreach (['g', 'kg', 'libra', 'ml', 'L', 'unidades', 'docena'] as $unitOption)<option value="{{ $unitOption }}">@endforeach
            </datalist>
            <datalist id="shopping-items-list">
                @foreach ($shoppingItems as $item)
                    <option value="{{ $item->name }}">
                @endforeach
            </datalist>
            @if (empty($ingredients))
                <div class="md-form-empty"><i class="bi bi-basket2" aria-hidden="true"></i><p>Agrega los ingredientes necesarios para preparar esta receta.</p></div>
            @endif
            <button wire:click="addIngredient" type="button" class="md-btn-text mt-2">
                <i class="bi bi-plus-lg" aria-hidden="true"></i> Agregar ingrediente
            </button>
        </x-ui.form-dialog-section>

        <x-ui.form-dialog-section name="preparation" title="Preparación">
            <x-ui.textarea name="instructions" label="Instrucciones (opcional)" rows="10" wire:model="instructions" />
        </x-ui.form-dialog-section>

        <x-ui.form-dialog-section name="nutrition" title="Costo y nutrición" description="Por porción. Se calculan desde los ingredientes cuando todos tienen precio y nutrición.">
            @if ($editingCalculation)
                <div class="recipe-calculation mb-3">
                    <div>
                        <span class="md-label-medium">Costo por porción</span>
                        <strong>{{ $editingCalculation['cost_per_serving'] !== null ? '$'.number_format($editingCalculation['cost_per_serving'], 0, ',', '.') : '—' }}</strong>
                    </div>
                    <div>
                        <span class="md-label-medium">Calorías calculadas</span>
                        <strong>{{ $editingCalculation['nutrition'] ? $editingCalculation['nutrition']['calories'].' kcal' : '—' }}</strong>
                    </div>
                    @if ($editingCalculation['missing_cost'] || $editingCalculation['missing_nutrition'])
                        <p class="md-body-small mb-0">
                            Para calcular todo faltan:
                            {{ collect($editingCalculation['missing_cost'])->merge($editingCalculation['missing_nutrition'])->unique()->implode(' · ') }}
                        </p>
                    @endif
                </div>
            @endif
            <p class="md-body-small meal-muted">
                {{ $nutritionSource === 'calculated' ? 'La nutrición actual se calculó desde los ingredientes; se recalcula al guardar.' : 'Valores manuales: se usan mientras falten datos de los ingredientes.' }}
            </p>
            <div class="md-field-pair">
                <x-ui.field name="nutritionCalories" label="Calorías" type="number" step="0.01" wire:model="nutritionCalories" />
                <x-ui.field name="nutritionProtein" label="Proteína (g)" type="number" step="0.01" wire:model="nutritionProtein" />
                <x-ui.field name="nutritionCarbs" label="Carbos (g)" type="number" step="0.01" wire:model="nutritionCarbs" />
                <x-ui.field name="nutritionFat" label="Grasa (g)" type="number" step="0.01" wire:model="nutritionFat" />
            </div>
            @if ($editingId)
                <div class="mt-4">
                    <x-ui.destructive-action label="Eliminar receta" action="delete('{{ $editingId }}')"
                                             title="Eliminar receta" message="La receta y sus ingredientes se eliminan de forma permanente." />
                </div>
            @endif
        </x-ui.form-dialog-section>
    </x-ui.form-dialog>
</x-module-shell>
