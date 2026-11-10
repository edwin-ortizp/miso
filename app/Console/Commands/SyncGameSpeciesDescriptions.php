<?php

namespace App\Console\Commands;

use App\Models\Game\GameSpecies;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class SyncGameSpeciesDescriptions extends Command
{
    protected $signature = 'game:sync-species-descriptions {--dry-run : Consulta sin escribir en el catálogo} {--number= : Número de especie}';

    protected $description = 'Completa descripciones vacías en español desde PokéAPI sin sobrescribir las existentes';

    public function handle(): int
    {
        $number = $this->option('number');
        if ($number !== null && (! ctype_digit((string) $number) || (int) $number < 1)) {
            $this->error('El número de especie debe ser un entero positivo.');

            return self::FAILURE;
        }
        $rows = GameSpecies::query()->when($number !== null, fn ($q) => $q->where('number', (int) $number))->orderBy('number')->get();
        $counts = ['completadas' => 0, 'conservadas' => 0, 'sin español' => 0, 'errores' => 0];
        $cache = Cache::store(config('game.description_cache_store', 'file'));
        foreach ($rows as $index => $species) {
            if (trim((string) $species->description) !== '') {
                $counts['conservadas']++;

                continue;
            }
            try {
                $data = $cache->remember('game:description:es:v1:'.$species->number, now()->addDays(7), function () use ($species) {
                    $response = Http::acceptJson()->connectTimeout(5)->timeout(10)->retry(2, 250, throw: false)
                        ->get('https://pokeapi.co/api/v2/pokemon-species/'.$species->number.'/');
                    $response->throw();
                    $payload = $response->json();
                    if (! is_array($payload) || ($payload['id'] ?? null) !== $species->number || ! is_array($payload['flavor_text_entries'] ?? null)) {
                        throw new \RuntimeException('Respuesta de especie inválida.');
                    }
                    $entries = collect($payload['flavor_text_entries'])->filter(fn ($e) => is_array($e) && ($e['language']['name'] ?? null) === 'es' && is_string($e['flavor_text'] ?? null)
                        && trim($e['flavor_text']) !== ''
                    )->sortBy(fn ($e) => sprintf('%08d:%s', (int) basename(rtrim($e['version']['url'] ?? '', '/')), $e['version']['name'] ?? ''));
                    $entry = $entries->first();
                    $text = $entry ? trim(preg_replace('/[\s\p{Z}]+/u', ' ', $entry['flavor_text'])) : null;
                    if ($text !== null && mb_strlen($text) > 5000) {
                        throw new \RuntimeException('Descripción demasiado larga.');
                    }

                    return ['description' => $text];
                });
                if (! ($data['description'] ?? null)) {
                    $counts['sin español']++;

                    continue;
                }
                if (! $this->option('dry-run')) {
                    // Evita sobrescribir una descripción editada durante la consulta de red.
                    $updated = GameSpecies::query()->whereKey($species->id)->where('description', $species->description)
                        ->update(['description' => $data['description']]);
                    if (! $updated) {
                        $counts['conservadas']++;

                        continue;
                    }
                }
                $counts['completadas']++;
            } catch (Throwable $exception) {
                $counts['errores']++;
                $this->warn('#'.$species->number.': '.$exception->getMessage());
            }
            if (($index + 1) % 50 === 0) {
                $this->line('Revisadas '.($index + 1).' de '.$rows->count().' especies.');
            }
        }
        $this->info(($this->option('dry-run') ? 'Simulación: ' : 'Resultado: ').collect($counts)->map(fn ($n, $k) => $k.'='.$n)->join(' · '));

        return $counts['errores'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
