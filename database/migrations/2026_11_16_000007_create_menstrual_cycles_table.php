<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menstrual_cycles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('started_on');
            $table->date('ended_on')->nullable();
            // Texto libre cifrado con el cast `encrypted` (CHG-130).
            $table->text('notes')->nullable();
            // Íntimo por defecto: las consultas amplias de MCP lo omiten salvo include_sensitive.
            $table->boolean('is_sensitive')->default(true);
            $table->string('source', 40)->nullable();
            $table->timestamps();

            // Nombres cortos explícitos: MySQL limita los identificadores a 64 caracteres (CHG-021).
            $table->unique(['user_id', 'started_on'], 'mc_user_start_unique');
            $table->unique(['id', 'user_id'], 'mc_id_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menstrual_cycles');
    }
};
