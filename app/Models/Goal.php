<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Goal extends Model
{
    use BelongsToUser, HasUuids;

    public function notifications(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(UserNotification::class, 'source');
    }

    public function reminders(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Reminder::class, 'source');
    }

    protected $fillable = [
        'title',
        'description',
        'status',
        'start_date',
        'due_date',
        'positive_count',
        'negative_count',
        'numeric_goal',
    ];

    protected function casts(): array
    {
        return [
            'numeric_goal' => 'array',
            'start_date' => 'date',
            'due_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(fn (self $goal) => $goal->taskAssociations()->delete());
    }

    public function goalEntries()
    {
        return $this->hasMany(GoalEntry::class);
    }

    public function goalNumericEntries()
    {
        return $this->hasMany(GoalNumericEntry::class);
    }

    public function taskAssociations(): MorphMany
    {
        return $this->morphMany(TaskAssociation::class, 'target');
    }

    public function tasks(): MorphToMany
    {
        return $this->morphToMany(Task::class, 'target', 'task_associations', 'target_id', 'task_id')
            ->withTimestamps();
    }
}
