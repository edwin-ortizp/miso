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

    /** Tabla semanal (CHG-123): segundo opt-in, solo útil si la persona también es visible. */
    public function leaderboard(int $userId): bool
    {
        return (bool) ($this->read($userId)['social_leaderboard'] ?? false);
    }

    /** ¿Ya se le ofreció elegir su @usuario al abrir Amigos por primera vez? (CHG-121) */
    public function usernamePrompted(int $userId): bool
    {
        return (bool) ($this->read($userId)['username_prompted'] ?? false);
    }

    public function markUsernamePrompted(int $userId): void
    {
        $this->save($userId, ['username_prompted' => true]);
    }

    public function setVisible(int $userId, bool $visible): void
    {
        $this->save($userId, ['social_visible' => $visible]);
    }

    public function setLeaderboard(int $userId, bool $enabled): void
    {
        $this->save($userId, ['social_leaderboard' => $enabled]);
    }

    /** @param array<string, mixed> $values */
    private function save(int $userId, array $values): void
    {
        $setting = ModuleSetting::withoutGlobalScopes()->firstOrNew(['user_id' => $userId, 'module' => self::MODULE]);
        $setting->user_id = $userId;
        $setting->settings = array_replace($setting->settings ?? [], $values);
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
