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
    public function roll(Collection $species, array $knownLines, array $weights, bool $forceNew, int $seed, ?string $featuredLine = null, int $featuredWeight = 1): ?GameSpecies
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

            // Línea destacada de la temporada: más probable dentro de su rareza (peso acotado por el validador).
            if ($featuredLine !== null && $featuredWeight > 1 && $candidates->contains('line_key', $featuredLine)) {
                $weighted = $candidates->flatMap(fn (GameSpecies $s) => array_fill(0, $s->line_key === $featuredLine ? $featuredWeight : 1, $s))->values();

                return $weighted[mt_rand(0, $weighted->count() - 1)];
            }

            return $candidates[mt_rand(0, $candidates->count() - 1)];
        } finally {
            mt_srand();
        }
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
