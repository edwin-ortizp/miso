<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Amistad entre dos cuentas (CHG-121). La fila pertenece a ambas, por eso no usa BelongsToUser:
 * toda lectura va por scopeForUser y por App\Services\Social.
 */
class Friendship extends Model
{
    public const PENDING = 'pending';
    public const ACCEPTED = 'accepted';
    public const BLOCKED = 'blocked';

    protected $fillable = ['requester_id', 'addressee_id', 'user_low_id', 'user_high_id', 'status', 'blocked_by', 'responded_at'];

    protected function casts(): array
    {
        return ['responded_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $friendship) {
            $friendship->user_low_id = min($friendship->requester_id, $friendship->addressee_id);
            $friendship->user_high_id = max($friendship->requester_id, $friendship->addressee_id);
        });
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function addressee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'addressee_id');
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where(fn ($q) => $q->where('requester_id', $userId)->orWhere('addressee_id', $userId));
    }

    public function scopeBetween(Builder $query, int $a, int $b): Builder
    {
        return $query->where('user_low_id', min($a, $b))->where('user_high_id', max($a, $b));
    }

    public function otherId(int $userId): int
    {
        return $this->requester_id === $userId ? $this->addressee_id : $this->requester_id;
    }
}
