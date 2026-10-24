<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Models\UserNotification;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class CreateNotification
{
    public function create(User $user, Model $source, string $type, string $occurrenceKey, string $title, string $body, CarbonInterface $scheduledFor): ?UserNotification
    {
        abort_unless((int) $source->user_id === (int) $user->id, 404);
        $notice = UserNotification::withoutGlobalScopes()->firstOrCreate([
            'user_id' => $user->id, 'source_type' => app(NotificationSource::class)->type($source),
            'source_id' => $source->id, 'type' => $type, 'occurrence_key' => $occurrenceKey,
        ], ['title' => $title, 'body' => $body, 'scheduled_for' => $scheduledFor->copy()->utc()]);
        // Reopening the same occurrence restores its row without losing reading history.
        if ($notice->cancelled_at && ! $notice->telegram_sent_at) {
            $notice->update(['cancelled_at' => null, 'scheduled_for' => $scheduledFor->copy()->utc()]);
        }

        return $notice->wasRecentlyCreated ? $notice : null;
    }
}
