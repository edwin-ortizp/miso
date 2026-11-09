<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_weeks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('week_start');
            $table->unsignedBigInteger('seed');
            $table->json('config_snapshot')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'week_start']);
        });

        Schema::create('game_team_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_week_id')->constrained('game_weeks')->cascadeOnDelete();
            $table->foreignId('companion_id')->constrained('game_companions')->cascadeOnDelete();
            $table->string('ability_key', 120);
            $table->string('ability_label', 120);
            $table->unsignedTinyInteger('slot');
            $table->timestamps();
            $table->unique(['game_week_id', 'companion_id']);
        });

        Schema::create('game_missions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_week_id')->constrained('game_weeks')->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('title', 255);
            $table->json('params')->nullable();
            $table->unsignedSmallInteger('target');
            $table->unsignedSmallInteger('progress')->default(0);
            $table->timestamp('half_rewarded_at')->nullable();
            $table->timestamp('full_rewarded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('game_mission_contributions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_mission_id')->constrained('game_missions')->cascadeOnDelete();
            $table->string('contribution_key', 191);
            $table->foreignId('game_event_id')->nullable()->constrained('game_events')->nullOnDelete();
            $table->timestamps();
            $table->unique(['game_mission_id', 'contribution_key'], 'game_mission_contrib_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_mission_contributions');
        Schema::dropIfExists('game_missions');
        Schema::dropIfExists('game_team_members');
        Schema::dropIfExists('game_weeks');
    }
};
