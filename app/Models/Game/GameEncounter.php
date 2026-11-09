<?php

namespace App\Models\Game;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Una ball: la especie se decide al concederla y nunca caduca. */
class GameEncounter extends Model
{
    use BelongsToUser;

    protected $fillable = ['user_id', 'game_event_id', 'species_id', 'seed', 'granted_at', 'opened_at', 'result'];

    protected function casts(): array
    {
        return ['granted_at' => 'datetime', 'opened_at' => 'datetime', 'result' => 'array', 'seed' => 'integer'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(GameEvent::class, 'game_event_id');
    }

    public function species(): BelongsTo
    {
        return $this->belongsTo(GameSpecies::class, 'species_id');
    }
}
