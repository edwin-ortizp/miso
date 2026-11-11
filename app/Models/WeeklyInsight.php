<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Hallazgos de una semana (CHG-070). `findings`: lista de {key, value, text}. */
class WeeklyInsight extends Model
{
    protected $fillable = ['user_id', 'week_start', 'findings', 'dismissed_at'];

    protected function casts(): array
    {
        return ['week_start' => 'date', 'findings' => 'array', 'dismissed_at' => 'datetime'];
    }
}
