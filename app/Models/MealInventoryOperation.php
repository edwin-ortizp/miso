<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class MealInventoryOperation extends Model
{
    use BelongsToUser, HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'result' => 'array', 'reversed' => 'boolean',
            'origin' => 'array', 'document_lines' => 'array',
            'effective_date' => 'date', 'effective_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $operation) {
            if ($operation->getRawOriginal('document_lines') !== null
                && $operation->isDirty(['id', 'user_id', 'created_at', 'operation_key', 'action', 'fingerprint', 'document_type', 'channel', 'reason', 'origin', 'effective_date', 'effective_at', 'document_lines', 'document_fingerprint', 'corrects_operation_id'])) {
                throw ValidationException::withMessages(['inventory' => 'El documento de inventario es inmutable; registra una corrección.']);
            }
        });
        static::deleting(function (self $operation) {
            if ($operation->document_lines !== null) {
                throw ValidationException::withMessages(['inventory' => 'No se puede borrar un documento de inventario.']);
            }
        });
    }

    public function movements()
    {
        return $this->hasMany(MealInventoryMovement::class, 'operation_id');
    }
}
