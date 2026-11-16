<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sleep_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->dateTime('slept_at');
            $table->dateTime('woke_at');
            // Calidad percibida 1–5 (opcional).
            $table->unsignedTinyInteger('quality')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_sensitive')->default(false);
            $table->string('source', 40)->nullable();
            $table->string('source_key')->nullable();
            $table->timestamps();

            // Nombres cortos explícitos: MySQL limita los identificadores a 64 caracteres (CHG-021).
            $table->index(['user_id', 'woke_at'], 'sl_user_woke_idx');
            $table->unique(['id', 'user_id'], 'sl_id_user_unique');
        });

        Schema::table('users', function (Blueprint $table) {
            // Meta de horas de sueño por noche (CHG-129).
            $table->decimal('daily_sleep_hours', 3, 1)->default(8);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('daily_sleep_hours');
        });
        Schema::dropIfExists('sleep_logs');
    }
};
