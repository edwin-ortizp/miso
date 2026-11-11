<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Reto del día (CHG-077): uno por usuario y fecha; el progreso se deriva de los datos del día. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_daily_challenges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('key', 40);
            $table->string('module', 40);
            $table->json('params')->nullable();
            $table->unsignedSmallInteger('target');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('rewarded_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_daily_challenges');
    }
};
