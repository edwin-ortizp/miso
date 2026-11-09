<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_seasons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->string('name', 60)->nullable();
            $table->string('narrative', 400)->nullable();
            $table->json('config')->nullable();
            $table->string('notes', 1000)->nullable();
            $table->json('errors')->nullable();
            $table->json('proposal')->nullable();
            $table->string('source', 20)->default('mcp');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('reverted_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status', 'starts_at'], 'game_seasons_user_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_seasons');
    }
};
