<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Camino de temporada (CHG-079): paradas entregadas, una por usuario, temporada y parada. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_season_nodes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('season_id')->constrained('game_seasons')->cascadeOnDelete();
            $table->unsignedTinyInteger('node');
            $table->json('reward');
            $table->timestamp('awarded_at');
            $table->timestamps();
            $table->unique(['user_id', 'season_id', 'node']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_season_nodes');
    }
};
