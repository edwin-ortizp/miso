<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Reminder extends Model
{
    use \App\Models\Traits\BelongsToUser, \App\Models\Traits\UsesUtcDates, HasUuids;

    protected $guarded = ['id'];

    public function source(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }
}
