<?php

namespace App\Models;

use App\Models\Traits\UsesUtcDates;
use Illuminate\Database\Eloquent\Model;

class AndroidInstallation extends Model
{
    use UsesUtcDates;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['token', 'token_hash', 'secret_hash'];

    protected function casts(): array
    {
        return ['token' => 'encrypted', 'active' => 'boolean', 'activated_at' => 'immutable_datetime'];
    }
}
