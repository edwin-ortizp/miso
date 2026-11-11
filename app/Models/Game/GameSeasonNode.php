<?php

namespace App\Models\Game;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

/** Parada del camino de temporada ya entregada (CHG-079). */
class GameSeasonNode extends Model
{
    use BelongsToUser;

    protected $fillable = ['user_id', 'season_id', 'node', 'reward', 'awarded_at'];

    protected function casts(): array
    {
        return ['node' => 'integer', 'reward' => 'array', 'awarded_at' => 'datetime'];
    }
}
