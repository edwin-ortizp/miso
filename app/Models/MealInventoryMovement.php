<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class MealInventoryMovement extends Model
{
    use BelongsToUser, HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['delta' => 'float', 'balance_before' => 'decimal:3', 'balance_after' => 'decimal:3', 'sequence' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $movement) {
            if ($movement->getRawOriginal('movement_type') === null) {
                return; // Legacy writers remain unchanged until their adapters are migrated.
            }
            $changes = array_diff(array_keys($movement->getDirty()), ['shopping_item_id', 'updated_at']);
            if ($changes || ($movement->isDirty('shopping_item_id') && $movement->shopping_item_id !== null)) {
                throw ValidationException::withMessages(['inventory' => 'El movimiento es inmutable; registra una compensación.']);
            }
        });
        static::deleting(function (self $movement) {
            if ($movement->movement_type !== null) {
                throw ValidationException::withMessages(['inventory' => 'No se puede borrar un movimiento de inventario.']);
            }
        });
    }
}
