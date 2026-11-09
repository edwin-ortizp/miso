<?php

namespace App\Models\Game;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameTeamMember extends Model
{
    use BelongsToUser;

    protected $fillable = ['user_id', 'game_week_id', 'companion_id', 'ability_key', 'ability_label', 'slot'];

    protected function casts(): array
    {
        return ['slot' => 'integer', 'companion_id' => 'integer'];
    }

    public function companion(): BelongsTo
    {
        return $this->belongsTo(GameCompanion::class, 'companion_id');
    }
}
