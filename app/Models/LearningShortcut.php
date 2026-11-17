<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LearningShortcut extends Model
{
    use BelongsToUser, HasUuids;

    protected $guarded = ['id'];
}
