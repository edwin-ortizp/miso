<?php

namespace App\Models\Game;

use App\Models\Traits\BelongsToUser;
use App\Models\UserActiveDay;
use App\Support\Game\RequestGameActivity;
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

    protected static function booted(): void
    {
        // CHG-041: avisa a la respuesta que hubo novedad del juego del usuario.
        static::created(function (GameEvent $event): void {
            if (auth()->check() && (int) $event->user_id === (int) auth()->id()) {
                app(RequestGameActivity::class)->touch();
            }
            // CHG-067: cada actividad cuenta como registro del día activo.
            if ($event->kind === self::KIND_ACTIVITY && (int) $event->user_id > 0) {
                try {
                    UserActiveDay::countRecord((int) $event->user_id);
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        });
    }

    protected function casts(): array
    {
        return ['effect' => 'array', 'config_snapshot' => 'array', 'occurred_at' => 'datetime'];
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
