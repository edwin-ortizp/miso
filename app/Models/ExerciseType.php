<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ExerciseType extends Model
{
    use \App\Models\Traits\ResolvesCategoryCatalog;

    use BelongsToUser, HasUuids;

    protected $fillable = [
        'name',
        'calories_per_hour',
        'steps_equivalent',
        'category',
        'icon',
        'legacy_id',
    ];

    public function logs()
    {
        return $this->hasMany(ExerciseLog::class);
    }

    protected static function booted(): void
    {
        static::deleted(fn (self $type) => app(\App\Services\Catalog\CatalogPicker::class)->forget('exercise', (string) $type->id));
    }
}
