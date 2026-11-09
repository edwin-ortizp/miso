<?php

namespace App\Models\Game;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Especie alcanzada por el usuario. Las etapas previas quedan registradas tras evolucionar. */
class GameDiscovery extends Model
{
    use BelongsToUser;

    protected $fillable = ['user_id', 'species_id', 'discovered_at'];

    protected function casts(): array
    {
        return ['discovered_at' => 'datetime'];
    }

    public function species(): BelongsTo
    {
        return $this->belongsTo(GameSpecies::class, 'species_id');
    }
}
