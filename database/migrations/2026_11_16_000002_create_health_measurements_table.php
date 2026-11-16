<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_measurements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Clave del catálogo HealthMeasurement::TYPES (p. ej. blood_pressure).
            $table->string('type', 40);
            $table->decimal('value', 8, 2);
            // Segundo valor de la medición (diastólica en la presión arterial).
            $table->decimal('value2', 8, 2)->nullable();
            // Pulso asociado a la medición, en latidos por minuto.
            $table->unsignedSmallInteger('pulse')->nullable();
            $table->string('unit', 20);
            $table->dateTime('measured_at');
            $table->string('context', 40)->nullable();
            $table->text('notes')->nullable();
            // Patrón de health_events: las consultas amplias de MCP lo omiten salvo include_sensitive.
            $table->boolean('is_sensitive')->default(false);
            $table->string('source', 40)->nullable();
            $table->string('source_key')->nullable();
            $table->timestamps();

            // Nombre corto explícito: MySQL limita los identificadores a 64 caracteres (CHG-021).
            $table->index(['user_id', 'type', 'measured_at'], 'hm_user_type_at_idx');
            $table->unique(['id', 'user_id'], 'hm_id_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_measurements');
    }
};
