<?php

namespace App\Livewire\Meal;

use App\Livewire\Concerns\WithMealCategories;

use App\Livewire\Concerns\WithManagementCard;
use App\Models\Brand;
use App\Models\PurchaseLine;
use App\Models\ShoppingItem;
use App\Models\ShoppingItemPrice;
use App\Models\ShoppingItemVariant;
use App\Models\StorageLocation;
use App\Models\Store;
use App\Services\Meal\CatalogNames;
use App\Services\Meal\IngredientImportService;
use App\Services\Meal\IngredientMergeService;
use App\Services\Meal\RecipeCalculator;
use App\Services\Meal\UnitConverter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Ingredientes')]
class MealIngredients extends Component
{
    use WithManagementCard, WithMealCategories;

    /** Unidades de captura aceptadas por unidad base; se convierten al guardar. */
    public const CONTENT_UNITS = [
        'g' => ['g' => 'g', 'kg' => 'kg', 'lb' => 'libra'],
        'ml' => ['ml' => 'ml', 'l' => 'L'],
        'unit' => ['unit' => 'und', 'docena' => 'docena', 'carton' => 'cartón (30)'],
    ];

    #[Url(as: 'q', history: true, keep: true)]
    public string $search = '';

    #[Url(as: 'ingredient')]
    public string $ingredientId = '';

    /** Categorías seleccionadas (varias); vacío = todas. */
    #[Url(as: 'categories', history: true, keep: true)]
    public array $categoryFilter = [];

    /** Ubicaciones seleccionadas (varias; '__none' = sin ubicación); vacío = todas. */
    #[Url(as: 'locations', history: true, keep: true)]
    public array $locationFilter = [];

    // '' todas, '__none' sin ningún precio, o el id de la tienda.
    #[Url(as: 'store', history: true, keep: true)]
    public string $storeFilter = '';

    // '' todos, 'with' con precio, 'without' sin precio.
    #[Url(as: 'price', history: true, keep: true)]
    public string $priceFilter = '';

    // '' todos, 'yes' en lista de compras, 'no' fuera de la lista.
    #[Url(as: 'cart', history: true, keep: true)]
    public string $cartFilter = '';

    // '' todos, 'pending' sin unidad base o con variantes sin contenido.
    #[Url(as: 'data', history: true, keep: true)]
    public string $dataFilter = '';

    // '' todos, 'used' usados en recetas, 'unused' sin usar en recetas.
    #[Url(as: 'usage', history: true, keep: true)]
    public string $usageFilter = '';

    public bool $showForm = false;

    // Asistente «Convertir en variante de…»
    public ?string $mergeSourceId = null;

    public string $mergeTargetId = '';

    public string $mergeFlavor = '';

    public bool $mergeKeepAlias = true;

    public bool $mergeSuggested = false;

    public ?string $editingId = null;

    // Product form fields
    public string $name = '';

    public string $baseUnit = '';

    public bool $baseUnitLocked = false;

    public $gramsPerPiece = null;

    public $stock = 0;

    public $minStock = null;

    public $toBuy = 0;

    public string $categoryId = '';

    public string $storageLocationId = '';

    public ?string $consumeBy = null;

    public string $status = 'available';

    public bool $nextPurchase = false;

    public $kcal = null;

    public $protein = null;

    public $carbs = null;

    public $fat = null;

    // Variants (dynamic rows), each with its own prices.
    public array $variants = [];

    /** Precios en lista plana; cada uno apunta a su variante por `variant_key`. */
    public array $prices = [];

    // Alternative names used by the bulk import assistant
    public array $aliases = [];

    public function updatedIngredientId(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStoreFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPriceFilter(): void
    {
        $this->resetPage();
    }

    public function updatedCartFilter(): void
    {
        $this->resetPage();
    }

    public function updatedDataFilter(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['ingredientId', 'categoryFilter', 'locationFilter', 'storeFilter', 'priceFilter', 'cartFilter', 'dataFilter', 'usageFilter']);
        $this->resetPage();
    }

    public function updatedBaseUnit(): void
    {
        foreach ($this->variants as $index => $variant) {
            $this->variants[$index]['content_unit'] = $this->defaultContentUnit();
        }
    }

    public function openForm(?string $id = null)
    {
        $this->resetValidation();

        if ($id) {
            $item = ShoppingItem::with(['variants.brand', 'variants.prices.store', 'aliases'])->find($id);
            if (! $item) {
                return;
            }

            $this->editingId = $item->id;
            $this->name = $item->name;
            $this->baseUnit = $item->base_unit ?? '';
            $this->baseUnitLocked = $item->base_unit !== null;
            $this->gramsPerPiece = $item->grams_per_piece;
            $this->stock = $item->stock ?? 0;
            $this->minStock = $item->min_stock;
            $this->toBuy = $item->to_buy ?? 0;
            $this->categoryId = $item->category_id ?? '';
            $this->storageLocationId = $item->storage_location_id ?? '';
            $this->consumeBy = $item->consume_by?->format('Y-m-d');
            $this->status = $item->status ?? 'available';
            $this->nextPurchase = $item->next_purchase ?? false;
            $this->kcal = $item->kcal;
            $this->protein = $item->protein;
            $this->carbs = $item->carbs;
            $this->fat = $item->fat;
            $this->prices = $item->variants->flatMap(fn (ShoppingItemVariant $v) => $v->prices->map(fn (ShoppingItemPrice $p) => [
                'id' => $p->id,
                'variant_key' => $v->id,
                'purchase_line_id' => $p->purchase_line_id,
                'store_id' => $p->store_id,
                // Entero sin decimales: el campo lo muestra con separador de miles ("4.500").
                'amount' => (string) round((float) $p->amount),
                'observed_on' => $p->observed_on->format('Y-m-d'),
                'source' => $p->source,
                'verified' => $p->isVerified(),
            ]))->sortByDesc('observed_on')->values()->toArray();
            $this->variants = $item->variants->map(fn (ShoppingItemVariant $v) => [
                'id' => $v->id,
                'key' => $v->id,
                'brand' => $v->brand?->name ?? '',
                'flavor' => $v->flavor ?? '',
                'packaging' => $v->packaging ?? '',
                'content' => $v->content,
                'content_unit' => $this->defaultContentUnit(),
                'units_per_pack' => $v->units_per_pack,
                'barcode' => $v->barcode ?? '',
                'is_preferred' => $v->is_preferred,
                'kcal' => $v->kcal,
            ])->toArray();
            $this->aliases = $item->aliases->map(fn ($alias) => [
                'id' => $alias->id,
                'alias' => $alias->alias,
            ])->toArray();
        } else {
            $this->reset(['editingId', 'name', 'baseUnit', 'baseUnitLocked', 'gramsPerPiece', 'stock', 'minStock', 'toBuy', 'categoryId', 'storageLocationId', 'consumeBy', 'status', 'nextPurchase', 'kcal', 'protein', 'carbs', 'fat', 'variants', 'prices', 'aliases']);
        }

        $this->showForm = true;
    }

    public function closeForm()
    {
        $this->showForm = false;
        $this->editingId = null;
    }

    public function addVariant()
    {
        $this->variants[] = [
            'id' => null, 'key' => 'new-'.Str::uuid(), 'brand' => '', 'flavor' => '', 'packaging' => '', 'content' => null, 'content_unit' => $this->defaultContentUnit(),
            'units_per_pack' => null, 'barcode' => '', 'is_preferred' => false, 'kcal' => null,
        ];
    }

    public function removeVariant(int $index)
    {
        $key = $this->variants[$index]['key'] ?? null;
        unset($this->variants[$index]);
        $this->variants = array_values($this->variants);
        $this->prices = array_values(array_filter($this->prices, fn ($price) => ($price['variant_key'] ?? null) !== $key));
    }

    public function addPrice(): void
    {
        if ($this->variants === []) {
            return;
        }

        $this->prices[] = [
            'id' => null, 'variant_key' => count($this->variants) === 1 ? $this->variants[0]['key'] : '', 'store_id' => '', 'amount' => null,
            'observed_on' => now()->toDateString(), 'source' => 'manual', 'verified' => false,
        ];
    }

    public function removePrice(int $index): void
    {
        unset($this->prices[$index]);
        $this->prices = array_values($this->prices);
    }

    public function addAlias(): void
    {
        $this->aliases[] = ['id' => null, 'alias' => ''];
    }

    public function removeAlias(int $index): void
    {
        unset($this->aliases[$index]);
        $this->aliases = array_values($this->aliases);
    }

    public function save(IngredientImportService $importer)
    {
        // Los precios de variantes que ya no están en el formulario se van con ellas.
        $variantKeys = array_column($this->variants, 'key');
        $this->prices = array_values(array_filter($this->prices, fn ($price) => blank($price['variant_key'] ?? null) || in_array($price['variant_key'], $variantKeys, true)));

        // El precio llega con separador de miles ("4.500") desde el campo formateado.
        foreach ($this->prices as $index => $price) {
            if (is_string($price['amount'] ?? null)) {
                $this->prices[$index]['amount'] = str_replace('.', '', $price['amount']);
            }
        }

        $this->validate([
            'name' => 'required|string|max:255',
            'categoryId' => ['nullable', \Illuminate\Validation\Rule::in(array_keys($this->categoryOptions))],
            'storageLocationId' => ['nullable', Rule::in(array_keys($this->storageLocationOptions()))],
            'baseUnit' => ['required', Rule::in(array_keys(ShoppingItem::BASE_UNITS))],
            'gramsPerPiece' => 'nullable|numeric|gt:0',
            'stock' => 'required|numeric|min:0',
            'minStock' => 'nullable|numeric|min:0',
            'toBuy' => 'required|numeric|min:0',
            'kcal' => 'nullable|numeric|min:0',
            'protein' => 'nullable|numeric|min:0',
            'carbs' => 'nullable|numeric|min:0',
            'fat' => 'nullable|numeric|min:0',
            'variants.*.brand' => 'nullable|string|max:255',
            'variants.*.flavor' => 'nullable|string|max:60',
            'variants.*.packaging' => ['nullable', Rule::in(array_keys(ShoppingItemVariant::PACKAGINGS))],
            'variants.*.content' => 'nullable|numeric|gt:0',
            'variants.*.units_per_pack' => 'nullable|integer|min:1',
            'variants.*.barcode' => 'nullable|string|max:255',
            'variants.*.kcal' => 'nullable|numeric|min:0',
            'prices.*.variant_key' => ['required', Rule::in(array_column($this->variants, 'key'))],
            'prices.*.store_id' => ['required', Rule::in(Store::pluck('id'))],
            'prices.*.amount' => 'required|numeric|min:0',
            'prices.*.observed_on' => 'required|date',
            'prices.*.source' => ['required', Rule::in(array_keys(ShoppingItemPrice::SOURCES))],
            'aliases.*.alias' => 'nullable|string|max:255',
        ], attributes: [
            'baseUnit' => 'unidad base',
            'storageLocationId' => 'ubicación',
            'prices.*.variant_key' => 'variante',
            'prices.*.store_id' => 'tienda',
            'prices.*.amount' => 'precio',
            'prices.*.observed_on' => 'fecha del precio',
        ]);

        $importer->assertNameAvailable(trim($this->name), $this->editingId);

        $nullable = fn ($value) => $value === '' || $value === null ? null : $value;
        $data = [
            'name' => trim($this->name),
            'grams_per_piece' => $this->baseUnit === 'g' ? $nullable($this->gramsPerPiece) : null,
            'stock' => $this->stock,
            'min_stock' => $nullable($this->minStock),
            'to_buy' => $this->toBuy,
            'category_id' => $this->categoryId ?: null,
            'storage_location_id' => $this->storageLocationId ?: null,
            'consume_by' => $this->consumeBy ?: null,
            'status' => $this->status,
            'next_purchase' => $this->nextPurchase,
            'kcal' => $nullable($this->kcal),
            'protein' => $nullable($this->protein),
            'carbs' => $nullable($this->carbs),
            'fat' => $nullable($this->fat),
        ];

        $item = DB::transaction(function () use ($data, $importer, $nullable) {
            if ($this->editingId) {
                $item = ShoppingItem::find($this->editingId);
                if (! $item) {
                    return null;
                }
                // Un producto no cambia de unidad base una vez definida.
                $item->update($data + ($item->base_unit ? [] : ['base_unit' => $this->baseUnit]));
            } else {
                $item = ShoppingItem::create($data + ['base_unit' => $this->baseUnit]);
            }

            $keptIds = [];
            $preferredSeen = false;
            foreach ($this->variants as $variant) {
                $content = $nullable($variant['content'] ?? null);
                if ($content !== null) {
                    $content = UnitConverter::toBase((float) $content, $variant['content_unit'] ?? null, $item->base_unit) ?? (float) $content;
                }

                $isPreferred = ! $preferredSeen && (bool) ($variant['is_preferred'] ?? false);
                $preferredSeen = $preferredSeen || $isPreferred;

                $variantData = [
                    'brand_id' => CatalogNames::brand($variant['brand'] ?? null)?->id,
                    'packaging' => $variant['packaging'] ?: null,
                    'flavor' => trim((string) ($variant['flavor'] ?? '')) ?: null,
                    'content' => $content,
                    'units_per_pack' => $nullable($variant['units_per_pack'] ?? null),
                    'barcode' => trim((string) ($variant['barcode'] ?? '')) ?: null,
                    'is_preferred' => $isPreferred,
                    'kcal' => $nullable($variant['kcal'] ?? null),
                ];

                $model = ! empty($variant['id']) ? $item->variants()->whereKey($variant['id'])->first() : null;
                $model ? $model->update($variantData) : $model = $item->variants()->create($variantData);
                $keptIds[] = $model->id;

                $keptPriceIds = [];
                foreach (array_filter($this->prices, fn ($price) => ($price['variant_key'] ?? null) === $variant['key']) as $price) {
                    $priceData = [
                        'store_id' => $price['store_id'],
                        'amount' => $price['amount'],
                        'observed_on' => $price['observed_on'],
                        'source' => $price['source'],
                    ];

                    $priceModel = ! empty($price['id']) ? $model->prices()->whereKey($price['id'])->first() : null;
                    if ($priceModel?->purchase_line_id) {
                        $keptPriceIds[] = $priceModel->id;

                        continue; // Ticket prices are edited through their purchase.
                    }
                    $priceModel ? $priceModel->fill($priceData) : $priceModel = $model->prices()->make($priceData);

                    if (($price['verified'] ?? false) && ! $priceModel->verified_at) {
                        $priceModel->fill(['verified_at' => now(), 'verified_by' => auth()->id()]);
                    } elseif (! ($price['verified'] ?? false)) {
                        $priceModel->fill(['verified_at' => null, 'verified_by' => null]);
                    }

                    $priceModel->save();
                    $keptPriceIds[] = $priceModel->id;
                }
                $model->prices()->whereNull('purchase_line_id')->whereNotIn('id', $keptPriceIds)->delete();
            }

            if (PurchaseLine::where('shopping_item_id', $item->id)->whereNotIn('shopping_item_variant_id', $keptIds)->exists()) {
                throw ValidationException::withMessages(['variants' => 'No puedes eliminar una presentación con compras registradas.']);
            }
            $item->variants()->whereNotIn('id', $keptIds)->delete();
            $importer->syncAliases($item, $this->aliases);

            return $item;
        });

        // Las recetas que usan este producto recalculan su nutrición con los datos nuevos.
        if ($item) {
            app(RecipeCalculator::class)->refreshRecipesUsing($item);
        }

        $this->closeForm();
    }

    public function toggleNextPurchase(string $id)
    {
        $item = ShoppingItem::find($id);
        if ($item) {
            $item->update(['next_purchase' => ! $item->next_purchase]);
        }
    }

    public function openMerge(string $id): void
    {
        $source = ShoppingItem::find($id);
        if (! $source) {
            return;
        }

        $this->resetValidation();
        $target = IngredientMergeService::suggestedTarget($source);
        $this->mergeSourceId = $source->id;
        $this->mergeTargetId = $target?->id ?? '';
        $this->mergeFlavor = $target ? IngredientMergeService::suggestedFlavor($source->name, $target->name) : '';
        $this->mergeKeepAlias = true;
        $this->mergeSuggested = $target !== null;
    }

    public function updatedMergeTargetId(): void
    {
        $this->mergeSuggested = false;
        $source = ShoppingItem::find($this->mergeSourceId);
        $target = $this->mergeTargetId ? ShoppingItem::find($this->mergeTargetId) : null;
        if ($source && $target && $this->mergeFlavor === '') {
            $this->mergeFlavor = IngredientMergeService::suggestedFlavor($source->name, $target->name);
        }
    }

    public function closeMerge(): void
    {
        $this->reset(['mergeSourceId', 'mergeTargetId', 'mergeFlavor', 'mergeKeepAlias', 'mergeSuggested']);
    }

    public function confirmMerge(IngredientMergeService $merger): void
    {
        $this->validate([
            'mergeTargetId' => ['required', Rule::in(ShoppingItem::where('id', '!=', $this->mergeSourceId)->pluck('id'))],
            'mergeFlavor' => 'nullable|string|max:60',
        ], [
            'mergeTargetId.required' => 'Elige el producto principal.',
            'mergeTargetId.in' => 'Elige el producto principal de la lista.',
        ]);

        $source = ShoppingItem::findOrFail($this->mergeSourceId);
        $merger->merge($source, ShoppingItem::findOrFail($this->mergeTargetId), $this->mergeFlavor, $this->mergeKeepAlias);

        if ($this->ingredientId === $source->id) {
            $this->ingredientId = '';
        }
        $this->closeMerge();
    }

    public function removeFilter(string $key, ?string $value = null): void
    {
        $this->resetPage();
        match ($key) {
            'ingredient' => $this->ingredientId = '',
            'category' => $this->categoryFilter = array_values(array_diff($this->categoryFilter, [$value])),
            'location' => $this->locationFilter = array_values(array_diff($this->locationFilter, [$value])),
            'store' => $this->storeFilter = '',
            'price' => $this->priceFilter = '',
            'cart' => $this->cartFilter = '',
            'usage' => $this->usageFilter = '',
            'data' => $this->dataFilter = '',
            default => null,
        };
    }

    /** Chips de la franja de filtros aplicados: [key, value, label, icon]. */
    private function appliedFilters($stores): array
    {
        $chips = [];
        if ($this->ingredientId !== '') {
            $name = ShoppingItem::whereKey($this->ingredientId)->value('name');
            $chips[] = ['key' => 'ingredient', 'value' => null, 'label' => 'Ingrediente: '.($name ?? 'no disponible'), 'icon' => 'bi-basket'];
        }
        foreach ($this->categoryFilter as $category) {
            $chips[] = ['key' => 'category', 'value' => $category, 'label' => 'Categoría: '.($this->categoryOptions[$category] ?? 'no disponible'), 'icon' => 'bi-tag'];
        }
        $locations = $this->locationFilterOptions();
        foreach ($this->locationFilter as $location) {
            $chips[] = ['key' => 'location', 'value' => $location, 'label' => 'Ubicación: '.($locations[$location] ?? 'no disponible'), 'icon' => 'bi-geo-alt'];
        }
        if ($this->storeFilter !== '') {
            $chips[] = ['key' => 'store', 'value' => null, 'label' => 'Tienda: '.($this->storeFilter === '__none' ? 'Sin tienda' : ($stores[$this->storeFilter] ?? 'no disponible')), 'icon' => 'bi-shop'];
        }
        $labels = [
            'price' => [$this->priceFilter, ['with' => 'Con precio', 'without' => 'Sin precio'], 'bi-currency-dollar'],
            'cart' => [$this->cartFilter, ['yes' => 'En la lista de compras', 'no' => 'Fuera de la lista de compras'], 'bi-cart3'],
            'usage' => [$this->usageFilter, ['used' => 'Usados en recetas', 'unused' => 'Sin usar en recetas'], 'bi-book'],
            'data' => [$this->dataFilter, ['pending' => 'Pendientes de completar'], 'bi-exclamation-diamond'],
        ];
        foreach ($labels as $key => [$value, $options, $icon]) {
            if (isset($options[$value])) {
                $chips[] = ['key' => $key, 'value' => null, 'label' => $options[$value], 'icon' => $icon];
            }
        }

        return $chips;
    }

    public function delete(string $id)
    {
        if (\App\Models\MealInventoryMovement::where('user_id', auth()->id())->where('shopping_item_id', $id)->exists()) {
            $this->addError('name', 'Este producto tiene movimientos de consumo o preparación y debe conservarse para su historial.');
            return;
        }
        if (PurchaseLine::where('shopping_item_id', $id)->exists()) {
            $this->addError('name', 'Este producto tiene compras registradas y no puede eliminarse.');

            return;
        }
        ShoppingItem::where('id', $id)->delete();
    }

    #[On('ingredients-imported')]
    #[On('stores-updated')]
    public function refreshIngredients(): void
    {
        // The event is enough to trigger a fresh render of the catalog.
    }

    /** Ubicaciones activas para asignar, más la actual del producto aunque esté archivada. */
    public function storageLocationOptions(): array
    {
        $current = $this->editingId ? ShoppingItem::whereKey($this->editingId)->value('storage_location_id') : null;

        return StorageLocation::options(include: [$current]);
    }

    public function locationFilterOptions(): array
    {
        return [StorageLocation::NONE => 'Sin ubicación'] + StorageLocation::options(include: StorageLocation::where('user_id', auth()->id())->pluck('id')->all());
    }

    private function defaultContentUnit(): string
    {
        return array_key_first(self::CONTENT_UNITS[$this->baseUnit] ?? ['' => '']);
    }

    public function render()
    {
        $base = ShoppingItem::where('user_id', auth()->id())
            ->when($this->ingredientId, fn ($q, $id) => $q->whereKey($id))
            ->when($this->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when($this->categoryFilter, fn ($q, $c) => $q->whereIn('category_id', \App\Models\MealCategory::expandSelection($c, auth()->id())))
            ->when($this->locationFilter, function ($q, $selected) {
                $ids = StorageLocation::expandSelection(array_values(array_diff($selected, [StorageLocation::NONE])), auth()->id());
                $q->where(fn ($lq) => $lq->whereIn('storage_location_id', $ids)
                    ->when(in_array(StorageLocation::NONE, $selected, true), fn ($none) => $none->orWhereNull('storage_location_id')));
            })
            ->when($this->storeFilter === '__none', fn ($q) => $q->whereDoesntHave('prices'))
            ->when($this->storeFilter !== '' && $this->storeFilter !== '__none', fn ($q) => $q->whereHas('prices', fn ($pq) => $pq->where('store_id', $this->storeFilter)))
            ->when($this->priceFilter === 'with', fn ($q) => $q->whereHas('prices'))
            ->when($this->priceFilter === 'without', fn ($q) => $q->whereDoesntHave('prices'))
            ->when($this->cartFilter === 'yes', fn ($q) => $q->where('next_purchase', true))
            ->when($this->cartFilter === 'no', fn ($q) => $q->where('next_purchase', false))
            ->when($this->dataFilter === 'pending', fn ($q) => $q->where(fn ($pending) => $pending
                ->whereNull('base_unit')
                ->orWhereHas('variants', fn ($vq) => $vq->whereNull('content'))))
            ->when($this->usageFilter === 'used', fn ($q) => $q->whereHas('recipeIngredients'))
            ->when($this->usageFilter === 'unused', fn ($q) => $q->whereDoesntHave('recipeIngredients'));

        $ingredients = (clone $base)->withOffers()->with('category')
            ->orderBy('name')
            ->paginate($this->perPage());

        $nameSuggestions = ! $this->editingId && $this->showForm && trim($this->name) !== ''
            ? CatalogNames::similar($this->name, ShoppingItem::pluck('name'))
            : collect();

        $stores = Store::orderBy('name')->pluck('name', 'id');
        $mergeSource = $this->mergeSourceId ? ShoppingItem::find($this->mergeSourceId) : null;
        $merge = $mergeSource ? [
            'source' => $mergeSource,
            'targets' => ShoppingItem::where('id', '!=', $mergeSource->id)->orderBy('name')->pluck('name', 'id')->all(),
            'variants' => $mergeSource->variants()->with('brand')->get(),
            'preview' => app(IngredientMergeService::class)->preview($mergeSource),
        ] : null;
        $appliedFilters = $this->appliedFilters($stores);

        return view('livewire.meal.meal-ingredients', [
            'purchaseHistory' => $this->showForm && $this->editingId ? ShoppingItem::where('user_id', auth()->id())->with('purchaseLines.purchase.store')->findOrFail($this->editingId)->purchaseHistorySummary() : null,
            'ingredients' => $ingredients,
            'catalogTotal' => ShoppingItem::query()->count(),
            'stores' => $stores,
            'brands' => $this->showForm ? Brand::orderBy('name')->pluck('name') : collect(),
            'nameSuggestions' => $nameSuggestions,
            'appliedFilters' => $appliedFilters,
            'merge' => $merge,
            'activeFilters' => count($appliedFilters),
        ]);
    }
}
