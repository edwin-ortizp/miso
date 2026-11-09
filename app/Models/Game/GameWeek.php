<?php

namespace App\Models\Game;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Semana de juego (lunes a domingo): equipo y misiones. Se crea al primer uso. */
class GameWeek extends Model
{
    use BelongsToUser;

    protected $fillable = ['user_id', 'week_start', 'seed', 'config_snapshot'];

    protected function casts(): array
    {
        return ['week_start' => 'date', 'seed' => 'integer', 'config_snapshot' => 'array'];
    }

    public function members(): HasMany
    {
        return $this->hasMany(GameTeamMember::class)->orderBy('slot');
    }

    public function missions(): HasMany
    {
        return $this->hasMany(GameMission::class)->orderBy('id');
    }
}
