<?php

namespace App\Models\Game;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

/** Misión semanal. El progreso solo sube; las recompensas parciales se conservan. */
class GameMission extends Model
{
    use BelongsToUser;

    public const TYPE_CATEGORY = 'category_tasks';

    public const TYPE_HABITS = 'habit_groups';

    public const TYPE_VARIETY = 'variety';

    public const TYPE_SPECIFIC = 'specific_tasks';

    public const SOURCE_AUTO = 'auto';

    public const SOURCE_DIRECTOR = 'director';

    protected $fillable = [
        'user_id', 'game_week_id', 'type', 'title', 'params', 'target', 'progress', 'source',
        'half_rewarded_at', 'full_rewarded_at',
    ];

    protected function casts(): array
    {
        return [
            'params' => 'array',
            'target' => 'integer',
            'progress' => 'integer',
            'half_rewarded_at' => 'datetime',
            'full_rewarded_at' => 'datetime',
        ];
    }

    public function halfThreshold(): int
    {
        return max(1, (int) ceil($this->target / 2));
    }

    public function isEditable(): bool
    {
        return $this->progress === 0 && ! $this->half_rewarded_at && ! $this->full_rewarded_at;
    }
}
