<?php

namespace App\Observers;

use App\Services\Notifications\ManageReminders;
use Illuminate\Database\Eloquent\Model;

class NotificationSourceObserver
{
    public function updated(Model $source): void
    {
        if ($source->wasChanged(['end_date', 'end_is_date', 'completed', 'is_recurrence_history', 'scheduled_on', 'status', 'due_date', 'is_archived', 'birthday_month', 'birthday_day', 'birthday_year'])) {
            app(ManageReminders::class)->refresh($source);
        }
    }

    public function deleted(Model $source): void
    {
        app(ManageReminders::class)->refresh($source, true);
    }
}
