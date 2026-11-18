<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudyReservationException extends Model
{
    public $timestamps = false;

    protected $fillable = ['original_date', 'date', 'changes'];

    protected function casts(): array
    {
        return ['changes' => 'array'];
    }
}
