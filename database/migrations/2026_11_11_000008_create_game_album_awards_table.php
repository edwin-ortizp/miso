<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Álbumes completos por usuario (CHG-078). Los álbumes que ya estaban completos antes de este
 * cambio se siembran sin ball (sin recompensa retroactiva).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_album_awards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('album_key', 40);
            $table->unsignedInteger('species_total');
            $table->timestamp('awarded_at');
            $table->timestamps();
            $table->unique(['user_id', 'album_key']);
        });

        $catalog = [];
        foreach (DB::table('game_species')->where('active', true)->whereNotNull('types')->get(['id', 'types']) as $row) {
            $catalog[(int) $row->id] = array_values(array_unique(array_filter((array) json_decode((string) $row->types, true), 'is_string')));
        }
        $totals = [];
        foreach ($catalog as $types) {
            foreach ($types as $type) {
                $totals[$type] = ($totals[$type] ?? 0) + 1;
            }
        }
        if ($totals === []) {
            return;
        }

        $found = [];
        foreach (['game_discoveries', 'game_companions'] as $table) {
            foreach (DB::table($table)->get(['user_id', 'species_id']) as $row) {
                $found[(int) $row->user_id][(int) $row->species_id] = true;
            }
        }
        $now = now();
        foreach ($found as $userId => $species) {
            $hits = [];
            foreach (array_keys($species) as $id) {
                foreach ($catalog[$id] ?? [] as $type) {
                    $hits[$type] = ($hits[$type] ?? 0) + 1;
                }
            }
            foreach ($hits as $type => $count) {
                if ($count >= $totals[$type]) {
                    DB::table('game_album_awards')->insertOrIgnore([
                        'user_id' => $userId, 'album_key' => $type, 'species_total' => $totals[$type],
                        'awarded_at' => $now, 'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('game_album_awards');
    }
};
