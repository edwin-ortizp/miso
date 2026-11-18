<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class HabitDefinition extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'name',
        'icon',
        'time_of_day',
        'goal_duration',
        'base_time',
        'show_in_calendar',
    ];

    public function goals(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Goal::class, 'goal_habits', 'habit_id', 'goal_id')->withTimestamps();
    }

    public function habitCompletions()
    {
        return $this->hasMany(HabitCompletion::class, 'habit_id');
    }

    /** Lo que ocurre en otro módulo al completar este hábito, si se configuró algo. */
    public function action()
    {
        return $this->hasOne(HabitAction::class, 'habit_id');
    }
}
