<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Racha de constancia (CHG-076): al cerrar un día se fija si cumplió y con qué umbral,
 * para que cambiar el umbral no reescriba el pasado. Columnas nullable, sin backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_active_days', function (Blueprint $table): void {
            $table->boolean('streak_met')->nullable()->after('records_count');
            $table->unsignedTinyInteger('streak_threshold')->nullable()->after('streak_met');
        });
    }

    public function down(): void
    {
        Schema::table('user_active_days', function (Blueprint $table): void {
            $table->dropColumn(['streak_met', 'streak_threshold']);
        });
    }
};
