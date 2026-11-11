<?php

namespace App\Services\Game;

use App\Models\Game\GameCompanion;
use App\Models\Game\GameDiscovery;
use App\Models\Game\GameEvent;
use App\Models\Game\GameSpecies;
use App\Models\Game\GameTeamMember;
use App\Models\Game\GameWeek;
use Illuminate\Support\Collection;

/** Datos de la ficha de una especie (Colección y guardianes, CHG-062). */
class SpeciesSheet
{
    public function __construct(
        private readonly SpeciesImageProvider $images,
        private readonly CompanionProgress $progress,
    ) {}

    /** Ids descubiertos (descubrimientos + ejemplares), como claves para búsquedas rápidas. */
    public static function discoveredLookup(): Collection
    {
        return GameDiscovery::query()->pluck('species_id')
            ->merge(GameCompanion::query()->pluck('species_id'))->unique()->values()->flip();
    }

    /** Especies del equipo de esta semana, como claves. */
    public static function teamLookup(): Collection
    {
        $week = GameWeek::query()->whereDate('week_start', WeekPlanner::weekStart(now())->toDateString())->first();
        if (! $week) {
            return collect();
        }

        return GameCompanion::query()
            ->whereIn('id', GameTeamMember::query()->where('game_week_id', $week->id)->pluck('companion_id'))
            ->pluck('species_id')->flip();
    }

    /** @return array<string, mixed>|null */
    public function build(int $speciesId, ?Collection $discoveredIds = null, ?Collection $teamLookup = null, int $historyLimit = 5): ?array
    {
        $species = GameSpecies::find($speciesId);
        if (! $species) {
            return null;
        }
        $discoveredIds ??= self::discoveredLookup();
        $teamLookup ??= self::teamLookup();

        $discovered = $discoveredIds->has($species->id);
        $companion = $discovered ? GameCompanion::query()->with('species')->where('line_key', $species->line_key)->first() : null;
        $chain = GameSpecies::query()->where('line_key', $species->line_key)->where('active', true)
            ->orderBy('stage')->orderBy('number')->get()
            ->map(fn (GameSpecies $item) => [
                'species' => $item,
                'discovered' => $discoveredIds->has($item->id),
                'image' => $this->images->url($item),
            ]);

        $history = collect();
        $historyTotal = 0;
        $options = collect();
        $xpPerLevel = max(1, (int) config('game.xp_per_level'));
        if ($companion) {
            $query = GameEvent::query()->where('effect->companion_id', $companion->id)->latest('occurred_at')->latest('id');
            $historyTotal = (clone $query)->count();
            $history = $query->limit($historyLimit)->get();
            $options = $this->progress->options($companion);
        }

        return [
            'species' => $species,
            'image' => $this->images->url($species),
            'discovered' => $discovered,
            'companion' => $companion,
            'isCurrent' => $companion && $companion->species_id === $species->id,
            'power' => $companion ? $this->progress->power($companion) : null,
            'inTeam' => $companion !== null && $teamLookup->has($companion->species_id),
            'levelXp' => $companion ? $companion->xp % $xpPerLevel : 0,
            'xpPerLevel' => $xpPerLevel,
            'nextLevel' => $options->count() === 1 ? $options->first()->requiredLevel() : null,
            'branchOptions' => $options->count() > 1
                ? $options->filter(fn (GameSpecies $s) => $companion->level >= $s->requiredLevel())->values()
                : collect(),
            'isFinal' => $companion && $options->isEmpty(),
            'history' => $history,
            'historyTotal' => $historyTotal,
            'chain' => $chain,
        ];
    }
}
