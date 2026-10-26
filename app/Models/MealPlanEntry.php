<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class MealPlanEntry extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'user_id',
        'date',
        'meal_type',
        'scheduled_time',
        'duration_minutes',
        'notes',
        'calories',
        'status',
        'consumption_mode',
        'consumed_at',
        'consumption',
        'consumption_operation_id',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'duration_minutes' => 'integer',
            'consumed_at' => 'datetime',
            'consumption' => 'array',
        ];
    }

    public function items()
    {
        return $this->hasMany(MealPlanEntryItem::class)->orderBy('position');
    }

    public function calculatedCalories(): int
    {
        return (int) round($this->items->sum(function (MealPlanEntryItem $item) {
            if ($item->preparation_id) {
                return (float) ($item->preparation?->nutrition['calories'] ?? 0) * (float) $item->portions;
            }
            if (!$item->recipe_id) {
                return $item->calories ?? 0;
            }

            $calories = $item->recipe?->nutrition['calories'] ?? 0;

            return $calories * (float) ($item->portions ?? 1);
        }));
    }

    public function hasIncompleteCalories(): bool
    {
        return $this->items->contains(fn (MealPlanEntryItem $item) =>
            ($item->recipe_id && !isset($item->recipe?->nutrition['calories'])) || ($item->preparation_id && !isset($item->preparation?->nutrition['calories']))
        );
    }

    public function getEffectiveCaloriesAttribute(): int
    {
        return $this->calories ?? $this->calculatedCalories();
    }
}
