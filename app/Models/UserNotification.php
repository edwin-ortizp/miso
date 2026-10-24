<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use App\Services\Notifications\NotificationSource;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class UserNotification extends Model
{
    use \App\Models\Traits\UsesUtcDates, BelongsToUser, HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['scheduled_for' => 'immutable_datetime', 'read_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime', 'telegram_sent_at' => 'immutable_datetime'];
    }

    protected $table = 'notifications';

    public function source(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }

    public function destination(): string
    {
        if ($this->source_type && ! app(NotificationSource::class)->find($this->user_id, $this->source_type, $this->source_id)) {
            return route(match ($this->source_type) {
                'task' => 'tasks.list', 'plan' => 'plans', 'goal' => 'goals', 'relationship' => 'relationships', default => 'home',
            });
        }

        return match ($this->source_type) {
            'task' => route('tasks.list'),
            'plan' => route('plans.show', $this->source_id),
            'goal' => route('goals.show', $this->source_id),
            'relationship' => route('relationships.show', $this->source_id),
            default => route('home'),
        };
    }
}
