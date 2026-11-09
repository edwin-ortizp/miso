<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_species', function (Blueprint $table): void {
            $table->text('description')->nullable();
            $table->json('types')->nullable();
            $table->json('stats')->nullable();
            $table->unsignedInteger('evolves_from_number')->nullable();
            $table->unsignedSmallInteger('evolve_level')->nullable();
        });

        Schema::table('game_companions', function (Blueprint $table): void {
            $table->unsignedSmallInteger('level')->default(1);
        });

        Schema::create('game_discoveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('species_id')->constrained('game_species');
            $table->timestamp('discovered_at');
            $table->timestamps();
            $table->unique(['user_id', 'species_id']);
        });

        // Los ejemplares existentes (CHG-019) quedan registrados como descubiertos.
        \Illuminate\Support\Facades\DB::table('game_companions')->orderBy('id')->each(function ($companion): void {
            \Illuminate\Support\Facades\DB::table('game_discoveries')->insertOrIgnore([
                'user_id' => $companion->user_id,
                'species_id' => $companion->species_id,
                'discovered_at' => $companion->first_met_at,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_discoveries');
        Schema::table('game_companions', fn (Blueprint $table) => $table->dropColumn('level'));
        Schema::table('game_species', fn (Blueprint $table) => $table->dropColumn(['description', 'types', 'stats', 'evolves_from_number', 'evolve_level']));
    }
};
