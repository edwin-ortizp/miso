<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** CHG-071: hallazgos semanales calculados una vez por semana (único usuario + semana). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_insights', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('week_start');
            $table->json('findings');
            $table->dateTime('dismissed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'week_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_insights');
    }
};
