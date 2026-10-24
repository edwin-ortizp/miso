<?php

namespace App\Services\Notifications;

use App\Models\ModuleSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class NotificationPreferences
{
    public const DEFAULTS = ['birthdays' => false, 'tasks' => false, 'plans' => false];

    public function get(int $userId): array
    {
        $settings = ModuleSetting::withoutGlobalScopes()->where('user_id', $userId)->where('module', 'notifications')->value('settings');

        return array_replace(self::DEFAULTS, array_intersect_key($settings ?? [], self::DEFAULTS));
    }

    public function save(int $userId, array $settings): void
    {
        DB::transaction(function () use ($userId, $settings) {
            User::whereKey($userId)->lockForUpdate()->firstOrFail();
            $record = ModuleSetting::withoutGlobalScopes()->where('user_id', $userId)->where('module', 'notifications')->first() ?? new ModuleSetting;
            $record->forceFill(['user_id' => $userId, 'module' => 'notifications',
                'settings' => array_map('boolval', array_intersect_key($settings, self::DEFAULTS))])->save();
        });
    }
}
