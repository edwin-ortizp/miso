<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class GoalHabit extends Model
{
    use BelongsToUser;

    protected $fillable = ['goal_id', 'habit_id'];

    protected static function booted(): void
    {
        static::saving(function (self $link): void {
            if (! Goal::withoutGlobalScopes()->whereKey($link->goal_id)->where('user_id', $link->user_id)->exists()
                || ! HabitDefinition::withoutGlobalScopes()->whereKey($link->habit_id)->where('user_id', $link->user_id)->exists()) {
                throw new \InvalidArgumentException('El objetivo y el hábito deben pertenecer al mismo usuario.');
            }
        });
    }

    public static function link(Goal $goal, HabitDefinition $habit): self
    {
        if (! $goal->user_id || (int) $goal->user_id !== (int) $habit->user_id) {
            throw new \InvalidArgumentException('El objetivo y el hábito deben pertenecer al mismo usuario.');
        }
        return DB::transaction(function () use ($goal, $habit): self {
            Goal::withoutGlobalScopes()->whereKey($goal->id)->lockForUpdate()->firstOrFail();
            $link = static::withoutGlobalScopes()->where('user_id', $goal->user_id)->where('goal_id', $goal->id)->where('habit_id', $habit->id)->first();
            if ($link) return $link;
            $link = new static(['goal_id' => $goal->id, 'habit_id' => $habit->id]);
            $link->user_id = $goal->user_id;
            $link->save();
            return $link;
        });
    }
}
