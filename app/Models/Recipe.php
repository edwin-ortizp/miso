<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Recipe extends Model
{
    use BelongsToUser, HasUuids;

    protected $fillable = [
        'name',
        'description',
        'difficulty',
        'prep_time',
        'servings',
        'meal_type',
        'instructions',
        'nutrition',
        'nutrition_source',
        'favorite',
    ];

    protected function casts(): array
    {
        return [
            'nutrition' => 'array',
            'servings' => 'float',
        ];
    }

    /** Favorito pendiente de guardar: se escribe en `catalog_favorites` al guardar la receta (CHG-153). */
    private ?bool $pendingFavorite = null;

    protected static function booted(): void
    {
        static::saved(function (self $recipe) {
            if ($recipe->pendingFavorite !== null) {
                app(\App\Services\Catalog\CatalogPicker::class)->set('recipes', (string) $recipe->id, $recipe->pendingFavorite);
                $recipe->pendingFavorite = null;
            }
        });
        static::deleted(fn (self $recipe) => app(\App\Services\Catalog\CatalogPicker::class)->forget('recipes', (string) $recipe->id));
    }

    /** Favorita de la persona autenticada; vive en `catalog_favorites`, no en esta tabla. */
    public function getFavoriteAttribute(): bool
    {
        return $this->pendingFavorite ?? ($this->exists && app(\App\Services\Catalog\CatalogPicker::class)->isFavorite('recipes', (string) $this->id));
    }

    public function setFavoriteAttribute(mixed $value): void
    {
        $this->pendingFavorite = (bool) $value;
    }

    public function recipeIngredients()
    {
        return $this->hasMany(RecipeIngredient::class);
    }

    public function mealPlanItems()
    {
        return $this->hasMany(MealPlanEntryItem::class);
    }
}
