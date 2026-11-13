<?php

namespace App\Models;

use App\Models\Traits\UsesUtcDates;
use Illuminate\Database\Eloquent\Model;

class AndroidDelivery extends Model
{
    use UsesUtcDates;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['next_attempt_at' => 'immutable_datetime', 'claimed_at' => 'immutable_datetime', 'accepted_at' => 'immutable_datetime'];
    }
}
