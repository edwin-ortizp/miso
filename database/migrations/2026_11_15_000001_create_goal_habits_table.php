<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('goal_habits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('goal_id');
            $table->foreign(['goal_id', 'user_id'], 'gh_goal_user_fk')->references(['id', 'user_id'])->on('goals')->cascadeOnDelete();
            $table->unsignedBigInteger('habit_id');
            $table->foreign(['habit_id', 'user_id'], 'gh_habit_user_fk')->references(['id', 'user_id'])->on('habit_definitions')->cascadeOnDelete();
            $table->unique(['goal_id', 'habit_id'], 'gh_pair_unique');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goal_habits');
    }
};
