<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Models\Country;
use App\Services\Geography\LocationCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class ImportGeography extends Command
{
    protected $signature = 'geography:import {directory : Directorio con countryInfo.txt, admin1CodesASCII.txt y cities500.zip}';

    protected $description = 'Importa el catálogo geográfico de GeoNames';

    public function handle(): int
    {
        $directory = rtrim($this->argument('directory'), '/\\');
        if (! class_exists(ZipArchive::class)) {
            $this->error('La importación requiere la extensión ZIP de PHP.');

            return self::FAILURE;
        }

        foreach (['countryInfo.txt', 'admin1CodesASCII.txt', 'cities500.zip'] as $file) {
            if (! is_readable($directory.'/'.$file)) {
                $this->error("No se puede leer {$file}.");

                return self::FAILURE;
            }
        }

        $archive = new ZipArchive;
        if ($archive->open($directory.'/cities500.zip', ZipArchive::RDONLY) !== true) {
            $this->error('No se pudo abrir cities500.zip.');

            return self::FAILURE;
        }

        try {
            $countries = [];
            foreach ($this->rows(fopen($directory.'/countryInfo.txt', 'rb'), 5) as $row) {
                $countries[$row[0]] = ['code' => $row[0], 'name' => Str::limit($row[4], 255, '')];
            }
            $regions = [];
            foreach ($this->rows(fopen($directory.'/admin1CodesASCII.txt', 'rb'), 2) as $row) {
                $regions[$row[0]] = $row[1];
            }
            if ($countries === [] || $regions === []) {
                throw new RuntimeException('Los archivos de países y regiones deben contener datos.');
            }

            $total = DB::transaction(function () use ($archive, $countries, $regions): int {
                Country::query()->upsert(array_values($countries), ['code'], ['name']);
                $total = 0;
                $batch = [];
                foreach ($this->rows($archive->getStream('cities500.txt'), 19) as $row) {
                    if (! isset($countries[$row[8]])) {
                        continue;
                    }
                    $batch[] = [
                        'id' => (int) $row[0],
                        'name' => $row[1],
                        'normalized_name' => LocationCatalog::normalize($row[1]),
                        'country_code' => $row[8],
                        'region' => $regions[$row[8].'.'.$row[10]] ?? null,
                        'population' => (int) $row[14],
                    ];
                    $total++;
                    if (count($batch) === 500) {
                        City::query()->upsert($batch, ['id'], ['name', 'normalized_name', 'region', 'population']);
                        $batch = [];
                    }
                }
                City::query()->upsert($batch, ['id'], ['name', 'normalized_name', 'region', 'population']);

                if ($total === 0) {
                    throw new RuntimeException('El archivo de ciudades no contiene datos válidos.');
                }

                return $total;
            });
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            $archive->close();
        }

        $this->info(count($countries)." países y {$total} ciudades procesados.");

        return self::SUCCESS;
    }

    private function rows(mixed $stream, int $columns): iterable
    {
        if (! is_resource($stream)) {
            throw new RuntimeException('Falta un archivo de GeoNames o no se puede leer.');
        }

        try {
            while (($line = fgets($stream)) !== false) {
                if ($line[0] === '#' || trim($line) === '') {
                    continue;
                }
                $row = explode("\t", rtrim($line, "\r\n"));
                if (count($row) < $columns) {
                    throw new RuntimeException('Un archivo de GeoNames contiene una fila incompleta.');
                }

                yield $row;
            }
        } finally {
            fclose($stream);
        }
    }
}
