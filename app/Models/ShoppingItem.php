<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use App\Services\Meal\UnitConverter;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Producto genérico del catálogo (sin marca ni tamaño). Las marcas y presentaciones van en las variantes.
 */
class ShoppingItem extends Model
{
    use BelongsToUser, HasUuids;

    public const BASE_UNITS = UnitConverter::BASE_UNITS;

    public const CATEGORIES = [
        'frutas_verduras' => 'Frutas y verduras',
        'carnes' => 'Carnes y pescados',
        'lacteos' => 'Lácteos y huevos',
        'panaderia' => 'Panadería',
        'cereales' => 'Cereales y granos',
        'enlatados' => 'Enlatados y conservas',
        'condimentos' => 'Condimentos y salsas',
        'bebidas' => 'Bebidas',
        'congelados' => 'Congelados',
        'snacks' => 'Snacks y dulces',
        'limpieza' => 'Limpieza',
        'higiene' => 'Higiene personal',
        'mascotas' => 'Mascotas',
        'otros' => 'Otros',
    ];

    protected $fillable = [
        'name',
        'base_unit',
        'grams_per_piece',
        'stock',
        'min_stock',
        'to_buy',
        'category_id',
        'storage_location_id',
        'consume_by',
        'status',
        'next_purchase',
        'kcal',
        'protein',
        'carbs',
        'fat',
    ];

    protected function casts(): array
    {
        return [
            'consume_by' => 'date',
            'next_purchase' => 'boolean',
            'stock' => 'float',
            'min_stock' => 'float',
            'to_buy' => 'float',
            'grams_per_piece' => 'float',
            'kcal' => 'float',
            'protein' => 'float',
            'carbs' => 'float',
            'fat' => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $item) {
            if ($item->category_id !== null && ($item->isDirty('category_id') || $item->isDirty('user_id'))) {
                $userId = $item->user_id ?? auth()->id();
                if (! MealCategory::withoutGlobalScopes()->where('user_id', $userId)->whereKey($item->category_id)->exists()) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['category_id' => 'Selecciona una categoría de tu catálogo.']);
                }
            }
            if ($item->storage_location_id !== null && ($item->isDirty('storage_location_id') || $item->isDirty('user_id'))) {
                $userId = $item->user_id ?? auth()->id();
                if (! StorageLocation::withoutGlobalScopes()->where('user_id', $userId)->whereKey($item->storage_location_id)->exists()) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['storage_location_id' => 'Selecciona una ubicación de tu catálogo.']);
                }
            }
        });
    }

    public function storageLocation()
    {
        return $this->belongsTo(StorageLocation::class);
    }

    public function category()
    {
        return $this->belongsTo(MealCategory::class);
    }

    public function variants()
    {
        return $this->hasMany(ShoppingItemVariant::class);
    }

    public function purchaseLines()
    {
        return $this->hasMany(PurchaseLine::class);
    }

    public function purchaseHistorySummary(): array
    {
        $lines = $this->purchaseLines;
        $purchases = $lines->map->purchase->filter()->unique('id')->filter(fn ($purchase) => $purchase->purchased_at->lte(now()));
        $latest = $purchases->sortByDesc('purchased_at')->first();
        $line = $latest ? $lines->firstWhere('purchase_id', $latest->id) : null;

        return [
            'never_purchased' => $purchases->isEmpty(),
            'last_purchase' => $latest ? ['purchase_id' => $latest->id, 'purchased_at' => $latest->purchased_at->toIso8601String(), 'store' => $latest->store?->name, 'unit_price' => $line?->unit_price] : null,
            'counts' => collect([30, 60, 90])->mapWithKeys(fn ($days) => [$days => $purchases->filter(fn ($purchase) => $purchase->purchased_at->gte(now()->subDays($days)))->count()])->all(),
        ];
    }

    public function recipeIngredients()
    {
        return $this->hasMany(RecipeIngredient::class);
    }

    public function aliases()
    {
        return $this->hasMany(ShoppingItemAlias::class);
    }

    public function prices()
    {
        return $this->hasManyThrough(ShoppingItemPrice::class, ShoppingItemVariant::class);
    }

    /** Carga lo necesario para calcular ofertas sin consultas extra. */
    public function scopeWithOffers($query)
    {
        return $query->with(['variants.brand', 'variants.prices.store']);
    }

    public function isBelowMinimum(): bool
    {
        return $this->min_stock !== null && (float) $this->stock < (float) $this->min_stock;
    }

    /**
     * Ofertas vigentes: el último precio de cada variante en cada tienda.
     *
     * @return Collection<int, array{variant: ShoppingItemVariant, price: ShoppingItemPrice, per_base: ?float}>
     */
    public function offers(?string $storeId = null): Collection
    {
        return $this->variants
            ->flatMap(fn (ShoppingItemVariant $variant) => $variant->latestPrices()
                ->when($storeId, fn ($prices) => $prices->where('store_id', $storeId))
                ->map(fn (ShoppingItemPrice $price) => [
                    'variant' => $variant,
                    'price' => $price,
                    'per_base' => $variant->pricePerBase($price, $this->base_unit),
                ])
                ->values())
            ->values();
    }

    /**
     * Oferta usada para estimar: la variante preferida en su tienda más barata; sin preferida,
     * la más barata por unidad base; y si nada es comparable, el precio más bajo.
     */
    public function bestOffer(?string $storeId = null): ?array
    {
        $offers = $this->offers($storeId);
        if ($offers->isEmpty()) {
            return null;
        }

        $preferred = $offers->filter(fn ($offer) => $offer['variant']->is_preferred);
        if ($preferred->isNotEmpty()) {
            return $preferred->sortBy(fn ($offer) => (float) $offer['price']->amount)->first();
        }

        $comparable = $offers->filter(fn ($offer) => $offer['per_base'] !== null);
        if ($comparable->isNotEmpty()) {
            return $comparable->sortBy('per_base')->first();
        }

        return $offers->sortBy(fn ($offer) => (float) $offer['price']->amount)->first();
    }

    public function estimatedPrice(?string $storeId = null): ?float
    {
        $offer = $this->bestOffer($storeId);

        return $offer ? (float) $offer['price']->amount : null;
    }

    public function estimatedSubtotal(?string $storeId = null): ?float
    {
        $price = $this->estimatedPrice($storeId);

        return $price === null ? null : $price * max((float) $this->to_buy, 1);
    }

    /** Precio por unidad base (1 g, 1 ml o 1 unidad) de la mejor oferta comparable, para costear recetas. */
    public function costPerBaseUnit(): ?float
    {
        $offer = $this->bestOffer();
        if (! $offer || $offer['per_base'] === null) {
            return null;
        }

        return $offer['per_base'] / UnitConverter::comparisonSize($this->base_unit);
    }

    /** Nutrición por unidad base: la variante preferida manda si la define; si no, el producto. */
    public function nutritionPerBaseUnit(): ?array
    {
        $preferred = $this->variants->firstWhere('is_preferred', true);
        $source = $preferred && $preferred->kcal !== null ? $preferred : $this;
        if ($source->kcal === null || ! $this->base_unit) {
            return null;
        }

        $divisor = $this->base_unit === 'unit' ? 1 : 100;

        return collect(['calories' => $source->kcal, 'protein' => $source->protein, 'carbs' => $source->carbs, 'fat' => $source->fat])
            ->map(fn ($value) => $value === null ? 0.0 : (float) $value / $divisor)
            ->all();
    }

    public function formatQuantity(?float $quantity): string
    {
        return UnitConverter::format($quantity, $this->base_unit);
    }

    public function baseUnitLabel(): string
    {
        return match ($this->base_unit) {
            'g' => 'g',
            'ml' => 'ml',
            'unit' => 'und',
            default => '',
        };
    }
}
