<?php

namespace App\Models\Game;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

/** Álbum de tipo completado (CHG-078): evidencia de la recompensa, una fila por usuario y tipo. */
class GameAlbumAward extends Model
{
    use BelongsToUser;

    protected $fillable = ['user_id', 'album_key', 'species_total', 'awarded_at'];

    protected function casts(): array
    {
        return ['species_total' => 'integer', 'awarded_at' => 'datetime'];
    }
}
