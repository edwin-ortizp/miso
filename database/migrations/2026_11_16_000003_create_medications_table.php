<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->decimal('dose_amount', 8, 2)->nullable();
            $table->string('dose_unit', 20)->nullable();
            $table->string('form', 30)->nullable();
            // daily = horarios fijos cada día; every_hours = cada N horas desde el primer horario; weekdays = horarios en días elegidos.
            $table->string('frequency_type', 20);
            $table->json('times')->nullable();
            $table->unsignedTinyInteger('interval_hours')->nullable();
            // Días de la semana en formato Carbon (0 = domingo … 6 = sábado).
            $table->json('weekdays')->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('instructions')->nullable();
            $table->text('notes')->nullable();
            // Enlace opcional a un evento de salud. Sin FK compuesta: no admite nullOnDelete (CHG-126).
            $table->uuid('health_event_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_sensitive')->default(false);
            $table->timestamps();

            // Nombres cortos explícitos: MySQL limita los identificadores a 64 caracteres (CHG-021).
            $table->index(['user_id', 'is_active', 'starts_on'], 'med_user_active_idx');
            $table->unique(['id', 'user_id'], 'med_id_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medications');
    }
};
