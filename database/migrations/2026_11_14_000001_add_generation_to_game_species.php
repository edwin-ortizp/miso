<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $rows = json_decode(file_get_contents(base_path('pokemon.json')), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($rows) || ! array_is_list($rows) || $rows === []) {
            throw new RuntimeException('El catálogo debe ser una lista de especies.');
        }
        $catalog = [];
        foreach ($rows as $row) {
            if (! is_int($row['number'] ?? null) || $row['number'] < 1 || ! is_string($row['name'] ?? null)
                || trim($row['name']) === '' || ! is_int($row['generation'] ?? null)
                || $row['generation'] < 1 || $row['generation'] > 9 || isset($catalog[$row['number']])) {
                throw new RuntimeException('El catálogo de generaciones es inconsistente.');
            }
            $catalog[$row['number']] = $row;
        }
        // Validate everything before MySQL DDL (which commits implicitly).
        $existing = DB::table('game_species')->get(['number', 'name']);
        foreach ($existing as $species) {
            $source = $catalog[$species->number] ?? null;
            if (! $source || $source['name'] !== $species->name) {
                throw new RuntimeException('No coincide la especie #'.$species->number.'. Revisa el catálogo antes de migrar.');
            }
        }
        Schema::table('game_species', function (Blueprint $table): void {
            $table->unsignedTinyInteger('generation')->default(1)->index();
        });
        DB::transaction(function () use ($catalog, $existing): void {
            foreach ($existing->groupBy(fn ($s) => $catalog[$s->number]['generation']) as $generation => $species) {
                DB::table('game_species')->whereIn('number', $species->pluck('number'))->update(['generation' => $generation]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('game_species', function (Blueprint $table): void {
            $table->dropIndex(['generation']);
            $table->dropColumn('generation');
        });
    }
};
