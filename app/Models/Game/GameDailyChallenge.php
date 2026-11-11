<?php

namespace App\Models\Game;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

/** Reto del día (CHG-077): plantilla elegida, parámetros congelados y meta; uno por usuario y fecha. */
class GameDailyChallenge extends Model
{
    use BelongsToUser;

    protected $fillable = ['user_id', 'date', 'key', 'module', 'params', 'target', 'completed_at', 'rewarded_at'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'params' => 'array',
            'target' => 'integer',
            'completed_at' => 'datetime',
            'rewarded_at' => 'datetime',
        ];
    }
}
