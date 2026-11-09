<?php

namespace App\Services\Game;

use App\Models\Game\GameSeason;
use Carbon\CarbonInterface;

/**
 * Reglas vigentes en un momento: configuración base + temporada activa (si las
 * temporadas están habilitadas). Se leen al calcular, nunca de forma retroactiva.
 */
class GameRules
{
    public function __construct(private readonly GameSettings $settings) {}

    public function activeSeason(int $userId, CarbonInterface $at): ?GameSeason
    {
        if (! $this->settings->for($userId)['seasons_enabled']) {
            return null;
        }

        return GameSeason::withoutGlobalScopes()->where('user_id', $userId)
            ->where('status', GameSeason::ACTIVE)
            ->where('starts_at', '<=', $at)->where('ends_at', '>', $at)
            ->orderByDesc('starts_at')->first();
    }

    /**
     * @return array{season_id: ?int, mission_types: array<int, string>, mission_target: array{int, int}, mission_focus: array<int, string>, team_bonus: array<string, float>, rarity_weights: ?array<string, int>, featured_line: ?string, featured_weight: int}
     */
    public function for(int $userId, CarbonInterface $at): array
    {
        $season = $this->activeSeason($userId, $at);
        $config = $season?->config ?? [];

        return [
            'season_id' => $season?->id,
            'mission_types' => $config['mission_types'] ?? config('game.mission_types'),
            'mission_target' => $config['mission_target'] ?? config('game.mission_target'),
            'mission_focus' => $config['mission_focus'] ?? [],
            'team_bonus' => $config['team_bonus'] ?? [],
            'rarity_weights' => $config['rarity_weights'] ?? null,
            'featured_line' => $config['featured_line'] ?? null,
            'featured_weight' => (int) ($config['featured_weight'] ?? 1),
        ];
    }

    /**
     * Ajustes del usuario con la capa de la temporada (pesos y línea destacada).
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function overlay(int $userId, array $settings, CarbonInterface $at): array
    {
        $rules = $this->for($userId, $at);
        if ($rules['rarity_weights']) {
            $settings['rarity_weights'] = $rules['rarity_weights'];
        }
        $settings['featured_line'] = $rules['featured_line'];
        $settings['featured_weight'] = $rules['featured_weight'];
        $settings['season_id'] = $rules['season_id'];

        return $settings;
    }
}
