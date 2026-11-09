<?php

namespace App\Models\Game;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Registro inmutable de lo que pasó en el juego. `source_label` conserva el nombre
 * legible del origen aunque la tarea o el hábito se borre.
 */
class GameEvent extends Model
{
    use BelongsToUser;

    public const KIND_ACTIVITY = 'activity';
    public const KIND_DISCOVERED = 'discovered';
    public const KIND_XP_GAINED = 'xp_gained';
    public const KIND_MISSION_REWARD = 'mission_reward';

    protected $fillable = [
        'user_id', 'source_type', 'source_id', 'source_label', 'kind', 'effect',
        'config_snapshot', 'idempotency_key', 'occurred_at',
    ];

    protected function casts(): array
    {
        return ['effect' => 'array', 'config_snapshot' => 'array', 'occurred_at' => 'datetime'];
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
