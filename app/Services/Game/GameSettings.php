<?php

namespace App\Services\Game;

use App\Models\ModuleSetting;

/** Lee y guarda la configuración del juego de un usuario sobre los valores de config/game.php. */
class GameSettings
{
    public const MODULE = 'game';

    /** @return array{enabled: bool, sources: array<string, bool>, daily_limit: int, pity_threshold: int, rarity_weights: array<string, int>} */
    public function for(int $userId): array
    {
        $stored = ModuleSetting::withoutGlobalScopes()
            ->where('user_id', $userId)->where('module', self::MODULE)->first()?->settings;
        $stored = is_array($stored) ? $stored : [];
        $defaults = config('game.defaults');

        $settings = array_replace($defaults, array_intersect_key($stored, $defaults));
        $settings['sources'] = array_map('boolval', array_replace($defaults['sources'], (array) ($stored['sources'] ?? [])));
        $settings['rarity_weights'] = array_map(
            fn ($weight) => max(0, (int) $weight),
            array_replace($defaults['rarity_weights'], array_intersect_key((array) ($stored['rarity_weights'] ?? []), $defaults['rarity_weights'])),
        );
        $settings['enabled'] = (bool) $settings['enabled'];
        $settings['director_enabled'] = (bool) $settings['director_enabled'];
        $settings['seasons_enabled'] = (bool) $settings['seasons_enabled'];
        $settings['celebrations'] = (bool) $settings['celebrations'];
        $settings['sounds'] = (bool) $settings['sounds'];
        $settings['celebration_set'] = array_values(array_intersect(
            array_keys(config('game.celebration_catalog')),
            array_map('strval', (array) $settings['celebration_set']),
        ));
        $settings['daily_limit'] = $this->clamp('daily_limit', (int) $settings['daily_limit']);
        $settings['pity_threshold'] = $this->clamp('pity_threshold', (int) $settings['pity_threshold']);

        return $settings;
    }

    /** @param array<string, mixed> $settings */
    public function save(int $userId, array $settings): void
    {
        $setting = ModuleSetting::withoutGlobalScopes()->firstOrNew(['user_id' => $userId, 'module' => self::MODULE]);
        $setting->user_id = $userId;
        $setting->settings = array_replace($this->for($userId), $settings);
        $setting->save();
    }

    private function clamp(string $key, int $value): int
    {
        [$min, $max] = config("game.limits.{$key}");

        return max($min, min($max, $value));
    }
}
