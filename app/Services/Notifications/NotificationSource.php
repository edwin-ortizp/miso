<?php

namespace App\Services\Notifications;

use App\Models\Goal;
use App\Models\Plan;
use App\Models\Relationship;
use App\Models\Task;
use App\Models\UserNotification;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

class NotificationSource
{
    public const TYPES = ['task' => Task::class, 'plan' => Plan::class, 'goal' => Goal::class, 'relationship' => Relationship::class];

    public const TIMEZONE = 'America/Bogota';

    public function type(Model $source): string
    {
        if (! in_array($source::class, self::TYPES, true)) {
            throw new \InvalidArgumentException('Origen no admitido.');
        }

        return $source->getMorphClass();
    }

    public function find(int $userId, string $type, string $id): ?Model
    {
        $class = \Illuminate\Database\Eloquent\Relations\Relation::getMorphedModel($type) ?? $type;
        $class = in_array($class, self::TYPES, true) ? $class : null;

        return $class ? $class::withoutGlobalScopes()->where('user_id', $userId)->whereKey($id)->first() : null;
    }

    public function active(Model $source): bool
    {
        return match (true) {
            $source instanceof Task => ! $source->completed && ! $source->is_recurrence_history,
            $source instanceof Plan => $source->status === 'scheduled',
            $source instanceof Goal => $source->status === 'active',
            $source instanceof Relationship => ! $source->is_archived,
            default => false,
        };
    }

    public function anchor(Model $source): ?CarbonImmutable
    {
        $date = match (true) {
            $source instanceof Task => $source->end_date,
            $source instanceof Plan => $source->scheduled_on,
            $source instanceof Goal => $source->due_date,
            default => null,
        };
        if (! $date) {
            return null;
        }
        $local = CarbonImmutable::parse($date->format('Y-m-d H:i:s'), self::TIMEZONE);

        return $source instanceof Task && ! $source->end_is_date ? $local : $local->setTime(9, 0);
    }

    public function cancelFor(Model $source, ?string $reminderId = null): void
    {
        UserNotification::withoutGlobalScopes()->where('user_id', $source->user_id)
            ->where('source_type', $this->type($source))->where('source_id', $source->id)
            ->whereNull('telegram_sent_at')->whereNull('cancelled_at')
            ->when($reminderId, fn ($q) => $q->where('type', 'reminder')->where('occurrence_key', 'like', $reminderId.':%'))
            ->update(['cancelled_at' => now('UTC')]);
    }
}
