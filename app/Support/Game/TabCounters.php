<?php

namespace App\Support\Game;

use App\Models\Game\GameCompanion;
use App\Models\Game\GameDiscovery;
use App\Models\Game\GameEncounter;
use App\Models\Game\GameMission;
use App\Models\Game\GameSpecies;
use App\Models\Game\GameWeek;
use App\Services\Game\GameRules;
use App\Services\Game\WeekPlanner;

/**
 * Contadores de las pestañas y del menú del juego. Cada método devuelve
 * `['text' => visible, 'label' => accesible]` o null para no mostrar nada.
 * Se memorizan por petición para no repetir consultas entre pestañas y menú.
 */
class TabCounters
{
    /** @var array<string, array{text: string, label: string}|null> */
    private array $cache = [];

    public function balls(): ?array
    {
        return $this->remember('balls', function () {
            $count = GameEncounter::query()->whereNull('opened_at')->count();

            return $count > 0 ? ['text' => (string) $count, 'label' => "{$count} por abrir"] : null;
        });
    }

    public function collection(): ?array
    {
        return $this->remember('collection', function () {
            $total = GameSpecies::query()->where('active', true)->count();
            if ($total === 0) {
                return null;
            }
            $found = GameDiscovery::query()->pluck('species_id')
                ->merge(GameCompanion::query()->pluck('species_id'))->unique()->count();

            return ['text' => "{$found}/{$total}", 'label' => "{$found} de {$total} descubiertas"];
        });
    }

    public function week(): ?array
    {
        return $this->remember('week', function () {
            // Solo lee: no crea la semana por mirar el menú.
            $week = GameWeek::query()->whereDate('week_start', WeekPlanner::weekStart(now())->toDateString())->first();
            if (! $week) {
                return null;
            }
            $missions = GameMission::query()->where('game_week_id', $week->id)->get(['full_rewarded_at']);
            if ($missions->isEmpty()) {
                return null;
            }
            $done = $missions->whereNotNull('full_rewarded_at')->count();

            return ['text' => "{$done}/{$missions->count()}", 'label' => "{$done} de {$missions->count()} misiones cumplidas"];
        });
    }

    public function season(): ?array
    {
        return $this->remember('season', function () {
            $season = app(GameRules::class)->activeSeason((int) auth()->id(), now());
            if (! $season) {
                return null;
            }
            $days = max(1, (int) ceil(now()->diffInHours($season->ends_at) / 24));

            return ['text' => "{$days} d", 'label' => "{$days} días restantes"];
        });
    }

    private function remember(string $key, callable $compute): ?array
    {
        return array_key_exists($key, $this->cache) ? $this->cache[$key] : ($this->cache[$key] = $compute());
    }
}
