<?php

namespace App\Services\Game;

use App\Models\Game\GameCompanion;
use App\Models\Game\GameDiscovery;
use App\Models\Game\GameEncounter;
use App\Models\Game\GameSpecies;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Shared species, explicit user availability (including unauthenticated jobs). */
class SpeciesCatalog
{
    public const NAMES = [1 => 'Primera', 'Segunda', 'Tercera', 'Cuarta', 'Quinta', 'Sexta', 'Séptima', 'Octava', 'Novena'];

    public function available(int $userId): Builder
    {
        return GameSpecies::query()->where('active', true)
            ->whereIn('generation', app(GameSettings::class)->for($userId)['enabled_generations']);
    }

    public function discoveredIds(int $userId): Collection
    {
        return GameDiscovery::withoutGlobalScopes()->where('user_id', $userId)->pluck('species_id')
            ->merge(GameCompanion::withoutGlobalScopes()->where('user_id', $userId)->pluck('species_id'))
            ->unique()->values();
    }

    public function knownIds(int $userId): array
    {
        return $this->discoveredIds($userId)->merge(GameEncounter::withoutGlobalScopes()
            ->where('user_id', $userId)->pluck('species_id'))->unique()->all();
    }

    /** Entry species and obtainable undiscovered alternatives. */
    public function encounters(int $userId): Collection
    {
        $available = $this->available($userId)->orderBy('number')->get();
        $byNumber = $available->keyBy('number');
        $byLine = $available->groupBy('line_key');
        $found = $this->discoveredIds($userId)->flip();
        $companions = GameCompanion::withoutGlobalScopes()->where('user_id', $userId)->get()->keyBy('line_key');

        return $available->filter(function (GameSpecies $s) use ($byNumber, $byLine, $found, $companions) {
            $predecessors = $s->evolves_from_number
                ? collect([$byNumber->get($s->evolves_from_number)])->filter()
                : $byLine[$s->line_key]->where('stage', $s->stage - 1);
            if ($predecessors->isEmpty()) {
                return true;
            }
            $companion = $companions->get($s->line_key);

            return ! $found->has($s->id) && $companion && $companion->level >= $s->requiredLevel()
                && $predecessors->contains(fn ($parent) => $found->has($parent->id));
        })->values();
    }

    public function generations(int $userId): array
    {
        $found = $this->discoveredIds($userId)->flip();
        $enabled = app(GameSettings::class)->for($userId)['enabled_generations'];
        $groups = GameSpecies::query()->where('active', true)->orderBy('number')->get()->groupBy('generation');
        $result = [];
        foreach (self::NAMES as $generation => $name) {
            $rows = $groups->get($generation, collect());
            $hits = $rows->filter(fn ($s) => $found->has($s->id))->count();
            $result[] = ['generation' => $generation, 'name' => $name.' generación',
                'total' => $rows->count(), 'found' => $hits,
                'complete' => $rows->isNotEmpty() && $hits === $rows->count(),
                'enabled' => in_array($generation, $enabled, true),
                'min' => $rows->min('number'), 'max' => $rows->max('number'),
                'images' => $rows->where('stage', $rows->min('stage'))->take(3)
                    ->map(fn ($s) => ['name' => $s->name, 'url' => app(SpeciesImageProvider::class)->url($s)])->all()];
        }

        return $result;
    }

    public function recommendation(int $userId): ?array
    {
        $settings = app(GameSettings::class)->for($userId);
        $generations = $this->generations($userId);
        foreach ($generations as $current) {
            if (! $current['enabled'] || ! $current['complete'] || in_array($current['generation'], $settings['dismissed_generations'], true)) {
                continue;
            }
            foreach ($generations as $next) {
                if ($next['generation'] > $current['generation'] && ! $next['enabled'] && $next['total'] > 0) {
                    return ['completed' => $current, 'next' => $next];
                }
            }
        }

        return null;
    }
}
