<?php

namespace App\Services\Notifications;

use App\Models\Plan;
use App\Models\Relationship;
use App\Models\Reminder;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class GenerateNotifications
{
    public function run(User $user): array
    {
        return DB::transaction(function () use ($user) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $preferences = app(NotificationPreferences::class)->get($user->id);
            $helper = app(NotificationSource::class);
            $create = app(CreateNotification::class);
            $today = now(NotificationSource::TIMEZONE)->startOfDay();
            $tomorrow = $today->copy()->addDay()->toDateString();
            $available = $today->copy()->setTime(9, 0);
            $created = 0;
            $record = function ($source, string $type, string $body) use ($user, $helper, $create, $today, $available, &$created) {
                if (! $helper->active($source) || $available->isFuture()) {
                    return;
                }
                $occurrence = $type === 'birthday' ? $today->toDateString() : $helper->anchor($source)->format('Y-m-d H:i:s');
                $notice = $create->create($user, $source, $type, $occurrence,
                    match ($type) {
                        'birthday' => 'Cumpleaños de hoy', 'task_due' => 'Tarea para mañana', default => 'Plan para mañana'
                    }, $body, $available);
                if ($notice) {
                    $created++;
                }
            };
            if ($preferences['birthdays']) {
                Relationship::withoutGlobalScopes()->where('user_id', $user->id)->whereNotNull('birthday_month')->whereNotNull('birthday_day')
                    ->eachById(function ($person) use ($today, $record) {
                        if ($person->birthday()?->isToday($today)) {
                            $record($person, 'birthday', 'Hoy es el cumpleaños de una persona de tus relaciones.');
                        }
                    });
            }
            if ($preferences['tasks']) {
                Task::withoutGlobalScopes()->where('user_id', $user->id)->where('completed', false)->where('is_recurrence_history', false)
                    ->whereDate('end_date', $tomorrow)->eachById(fn ($task) => $record($task, 'task_due', 'Tienes una tarea que vence mañana.'));
            }
            if ($preferences['plans']) {
                Plan::withoutGlobalScopes()->where('user_id', $user->id)->where('status', 'scheduled')->whereDate('scheduled_on', $tomorrow)
                    ->eachById(fn ($plan) => $record($plan, 'plan_due', 'Tienes un plan programado para mañana.'));
            }
            Reminder::withoutGlobalScopes()->where('user_id', $user->id)
                ->eachById(function ($reminder) use ($user, $helper, &$created) {
                    $source = $helper->find($user->id, $reminder->source_type, $reminder->source_id);
                    if ($source) {
                        $notice = app(ManageReminders::class)->materialize($source, $reminder);
                        if ($notice) {
                            $created++;
                        }
                    }
                });

            return ['created' => $created, 'date' => $today->toDateString(), 'timezone' => NotificationSource::TIMEZONE];
        }, 3);
    }
}
