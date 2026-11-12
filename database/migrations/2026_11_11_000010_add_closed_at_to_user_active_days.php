<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cierre del día (CHG-070): cuándo el usuario tocó "Listo, terminé el día".
 * Columna nullable, sin backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_active_days', function (Blueprint $table): void {
            $table->timestamp('closed_at')->nullable()->after('records_count');
        });
    }

    public function down(): void
    {
        Schema::table('user_active_days', function (Blueprint $table): void {
            $table->dropColumn('closed_at');
        });
    }
};
