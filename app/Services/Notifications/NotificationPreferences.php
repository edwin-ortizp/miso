<?php

namespace App\Services\Notifications;

use App\Models\ModuleSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class NotificationPreferences
{
    public const DEFAULTS = [
        'birthdays' => false, 'tasks' => false, 'plans' => false,
        // CHG-067: avisos del guardián y sus franjas.
        'guardian_nudges' => false, 'nudge_morning' => true, 'nudge_afternoon' => true, 'nudge_night' => false,
        // CHG-067: qué otros tipos de aviso también llegan al celular.
        'push_birthday' => false, 'push_task_due' => false, 'push_plan_due' => false, 'push_reminder' => false,
        // CHG-070: el resumen del domingo llega al celular por defecto.
        'push_weekly_insights' => true,
    ];

    /** ¿Este tipo de notificación debe salir por push para este usuario? */
    public static function pushAllowed(array $preferences, string $type): bool
    {
        return $type === 'guardian_nudge'
            ? (bool) $preferences['guardian_nudges']
            : (bool) ($preferences['push_'.$type] ?? false);
    }

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
