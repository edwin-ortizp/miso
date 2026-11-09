<?php

namespace App\Models\Game;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ejemplar propio: uno por línea evolutiva y usuario. */
class GameCompanion extends Model
{
    use BelongsToUser;

    protected $fillable = ['user_id', 'line_key', 'species_id', 'xp', 'level', 'first_met_at'];

    protected function casts(): array
    {
        return ['xp' => 'integer', 'level' => 'integer', 'species_id' => 'integer', 'first_met_at' => 'datetime'];
    }

    public function species(): BelongsTo
    {
        return $this->belongsTo(GameSpecies::class, 'species_id');
    }
}
