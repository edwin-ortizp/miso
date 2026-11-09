<?php

namespace App\Console\Commands;

use App\Models\Game\GameSpecies;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Importa el catálogo de especies desde un JSON (lista de objetos). Valida todo
 * antes de escribir: si una fila es inválida no se guarda nada. Actualiza por número.
 */
class ImportGameSpecies extends Command
{
    protected $signature = 'game:import-species {file : Ruta al archivo JSON}';

    protected $description = 'Importa o actualiza el catálogo de especies del juego desde un JSON';

    public function handle(): int
    {
        $path = (string) $this->argument('file');
        if (! is_file($path)) {
            $this->error("No existe el archivo: {$path}");

            return self::FAILURE;
        }

        $rows = json_decode((string) file_get_contents($path), true);
        if (! is_array($rows) || ! array_is_list($rows) || $rows === []) {
            $this->error('El archivo debe contener una lista JSON de especies.');

            return self::FAILURE;
        }

        $validator = Validator::make(['species' => $rows], [
            'species.*.number' => ['required', 'integer', 'min:1', 'distinct'],
            'species.*.name' => ['required', 'string', 'max:120'],
            'species.*.line_key' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9_-]+$/'],
            'species.*.stage' => ['nullable', 'integer', 'min:1', 'max:9'],
            'species.*.rarity' => ['nullable', Rule::in(config('game.rarities'))],
            'species.*.image_key' => ['nullable', 'string', 'max:255'],
            'species.*.active' => ['nullable', 'boolean'],
            'species.*.description' => ['nullable', 'string', 'max:5000'],
            'species.*.types' => ['nullable', 'array'],
            'species.*.types.*' => ['string', 'max:40'],
            'species.*.base_stats' => ['nullable', 'array'],
            'species.*.base_stats.*' => ['integer', 'min:0', 'max:1000'],
            'species.*.evolves_from' => ['nullable'],
            'species.*.evolve_level' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        // `evolves_from` puede venir como número o como nombre de otra especie del archivo o del catálogo.
        $numbersByName = GameSpecies::query()->pluck('number', 'name')->mapWithKeys(fn ($n, $name) => [mb_strtolower($name) => $n])->all();
        foreach ($rows as $row) {
            $numbersByName[mb_strtolower($row['name'])] = $row['number'];
        }
        $resolveFrom = function (mixed $from) use ($numbersByName): ?int {
            if ($from === null || $from === '') {
                return null;
            }

            return is_int($from) ? $from : ($numbersByName[mb_strtolower((string) $from)] ?? null);
        };

        DB::transaction(function () use ($rows, $resolveFrom): void {
            foreach ($rows as $row) {
                GameSpecies::updateOrCreate(['number' => $row['number']], [
                    'name' => $row['name'],
                    'line_key' => $row['line_key'],
                    'stage' => $row['stage'] ?? 1,
                    'rarity' => $row['rarity'] ?? 'common',
                    'image_key' => $row['image_key'] ?? null,
                    'active' => $row['active'] ?? true,
                    'description' => $row['description'] ?? null,
                    'types' => $row['types'] ?? null,
                    'stats' => $row['base_stats'] ?? null,
                    'evolves_from_number' => $resolveFrom($row['evolves_from'] ?? null),
                    'evolve_level' => $row['evolve_level'] ?? null,
                ]);
            }
        });

        $this->info(count($rows).' especies importadas.');

        return self::SUCCESS;
    }
}
