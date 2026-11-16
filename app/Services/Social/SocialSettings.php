<?php

namespace App\Services\Social;

use App\Models\ModuleSetting;

/** Ajustes sociales de una cuenta. Siempre explícito por usuario: lo lee también quien no es su dueño. */
class SocialSettings
{
    public const MODULE = 'social';

    public function visible(int $userId): bool
    {
        return (bool) ($this->read($userId)['social_visible'] ?? false);
    }

    public function setVisible(int $userId, bool $visible): void
    {
        $setting = ModuleSetting::withoutGlobalScopes()->firstOrNew(['user_id' => $userId, 'module' => self::MODULE]);
        $setting->user_id = $userId;
        $setting->settings = array_replace($setting->settings ?? [], ['social_visible' => $visible]);
        $setting->save();
    }

    /** @return array<string, mixed> */
    private function read(int $userId): array
    {
        $stored = ModuleSetting::withoutGlobalScopes()
            ->where('user_id', $userId)->where('module', self::MODULE)->first()?->settings;

        return is_array($stored) ? $stored : [];
    }
}
