<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_species', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('number')->unique();
            $table->string('name', 120);
            $table->string('line_key', 120)->index();
            $table->unsignedTinyInteger('stage')->default(1);
            $table->string('rarity', 20)->default('common');
            $table->string('image_key', 255)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('game_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source_type', 120)->nullable();
            $table->string('source_id', 64)->nullable();
            $table->string('source_label', 255)->nullable();
            $table->string('kind', 40);
            $table->json('effect')->nullable();
            $table->json('config_snapshot')->nullable();
            $table->string('idempotency_key', 191);
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key']);
            $table->index(['source_type', 'source_id']);
            $table->index(['user_id', 'kind', 'occurred_at']);
        });

        Schema::create('game_encounters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_event_id')->constrained('game_events')->cascadeOnDelete();
            $table->foreignId('species_id')->constrained('game_species');
            $table->unsignedBigInteger('seed');
            $table->timestamp('granted_at');
            $table->timestamp('opened_at')->nullable();
            $table->json('result')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'opened_at']);
            $table->index(['user_id', 'granted_at']);
        });

        Schema::create('game_companions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('line_key', 120);
            $table->foreignId('species_id')->constrained('game_species');
            $table->unsignedInteger('xp')->default(0);
            $table->timestamp('first_met_at');
            $table->timestamps();
            $table->unique(['user_id', 'line_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_companions');
        Schema::dropIfExists('game_encounters');
        Schema::dropIfExists('game_events');
        Schema::dropIfExists('game_species');
    }
};
