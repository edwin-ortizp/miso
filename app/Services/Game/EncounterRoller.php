<?php

namespace App\Services\Game;

use App\Models\Game\GameSpecies;
use Illuminate\Support\Collection;

/**
 * Elige la especie de una ball de forma determinista a partir de una semilla:
 * primero la rareza ponderada y luego la especie. Si se pide `forceNew`
 * (protección contra largas rachas sin novedad), elige entre líneas no conocidas.
 */
class EncounterRoller
{
    /**
     * @param  Collection<int, GameSpecies>  $species  especies activas de etapa 1
     * @param  array<int, string>  $knownLines  líneas que el usuario ya tiene o tiene pendientes
     * @param  array<string, int>  $weights
     */
    public function roll(Collection $species, array $knownLines, array $weights, bool $forceNew, int $seed, ?string $featuredLine = null, int $featuredWeight = 1, array $featuredTypes = []): ?GameSpecies
    {
        if ($species->isEmpty()) {
            return null;
        }

        mt_srand($seed);
        try {
            $pool = $species;
            if ($forceNew) {
                $unknown = $species->reject(fn (GameSpecies $s) => in_array($s->line_key, $knownLines, true));
                $pool = $unknown->isNotEmpty() ? $unknown : $species;
            }

            $byRarity = $pool->groupBy('rarity');
            $available = array_filter(
                $weights,
                fn ($weight, $rarity) => $weight > 0 && $byRarity->has($rarity),
                ARRAY_FILTER_USE_BOTH,
            );
            $rarity = $available === [] ? $byRarity->keys()->sort()->first() : $this->weighted($available);
            $candidates = $byRarity->get($rarity)->sortBy('number')->values();

            // Línea y tipos destacados de la temporada (CHG-045): más probables dentro de su rareza.
            // Pesos enteros: base 2, tipo destacado 2 x featured_type_weight, línea x featuredWeight.
            $lineApplies = $featuredLine !== null && $featuredWeight > 1 && $candidates->contains('line_key', $featuredLine);
            $typeApplies = $featuredTypes !== [] && $candidates->contains(fn (GameSpecies $s) => $this->hasType($s, $featuredTypes));
            if ($lineApplies || $typeApplies) {
                $typeWeight = (int) round(2 * (float) config('game.season.featured_type_weight', 1.5));
                $weighted = $candidates->flatMap(function (GameSpecies $s) use ($featuredLine, $featuredWeight, $featuredTypes, $lineApplies, $typeApplies, $typeWeight) {
                    $weight = ($typeApplies && $this->hasType($s, $featuredTypes) ? $typeWeight : 2)
                        * ($lineApplies && $s->line_key === $featuredLine ? $featuredWeight : 1);

                    return array_fill(0, $weight, $s);
                })->values();

                return $weighted[mt_rand(0, $weighted->count() - 1)];
            }

            return $candidates[mt_rand(0, $candidates->count() - 1)];
        } finally {
            mt_srand();
        }
    }

    /** @param array<int, string> $types slugs */
    private function hasType(GameSpecies $species, array $types): bool
    {
        foreach ((array) ($species->types ?? []) as $type) {
            if (in_array(\Illuminate\Support\Str::slug((string) $type), $types, true)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, int> $weights */
    private function weighted(array $weights): string
    {
        ksort($weights);
        $pick = mt_rand(1, array_sum($weights));
        foreach ($weights as $rarity => $weight) {
            $pick -= $weight;
            if ($pick <= 0) {
                return $rarity;
            }
        }

        return array_key_last($weights);
    }
}
