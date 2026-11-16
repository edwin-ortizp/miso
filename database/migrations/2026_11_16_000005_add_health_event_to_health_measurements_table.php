<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('health_measurements', function (Blueprint $table) {
            // Examen («Chequeo o examen») al que pertenece la medición. Sin FK compuesta: no admite nullOnDelete;
            // HealthEvent la pone en null al eliminarse (CHG-124).
            $table->uuid('health_event_id')->nullable()->after('context');
            $table->index(['user_id', 'health_event_id'], 'hm_user_event_idx');
        });
    }

    public function down(): void
    {
        Schema::table('health_measurements', function (Blueprint $table) {
            $table->dropIndex('hm_user_event_idx');
            $table->dropColumn('health_event_id');
        });
    }
};
