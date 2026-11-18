<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class City extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id', 'country_code', 'name', 'normalized_name', 'region', 'population'];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_code');
    }

    public function label(): string
    {
        return $this->region ? "{$this->name} · {$this->region}" : $this->name;
    }
}
