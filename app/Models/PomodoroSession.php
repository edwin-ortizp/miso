<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PomodoroSession extends Model
{
    use BelongsToUser, HasUuids;

    protected $fillable = [
        'task_id',
        'task_title',
        'date',
        'start_time',
        'end_time',
        'duration',
        'completed',
        'description',
        'client_token',
        'locked_by_device_id',
        'locked_at',
    ];

    public function task(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'start_time' => 'array',
            'end_time' => 'array',
            'completed' => 'boolean',
            'locked_at' => 'datetime',
        ];
    }
}
