<?php

namespace App\Models\Game;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

/**
 * Temporada propuesta por el director (Paseo vía MCP) o manual. Solo las
 * válidas quedan `active`; las inválidas se guardan `rejected` con motivos.
 * Revertir la termina desde ese momento, sin tocar el progreso.
 */
class GameSeason extends Model
{
    use BelongsToUser;

    public const ACTIVE = 'active';
    public const REJECTED = 'rejected';
    public const REVERTED = 'reverted';

    protected $fillable = [
        'user_id', 'status', 'name', 'narrative', 'config', 'notes', 'errors', 'proposal',
        'source', 'starts_at', 'ends_at', 'reverted_at',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'errors' => 'array',
            'proposal' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'reverted_at' => 'datetime',
        ];
    }

    /** Estado para mostrar: activa, programada, terminada, revertida o rechazada. */
    public function displayStatus(): string
    {
        return match (true) {
            $this->status === self::REJECTED => 'Rechazada',
            $this->status === self::REVERTED => 'Revertida',
            $this->starts_at?->isFuture() => 'Programada',
            $this->ends_at?->isPast() => 'Terminada',
            default => 'Activa',
        };
    }
}
