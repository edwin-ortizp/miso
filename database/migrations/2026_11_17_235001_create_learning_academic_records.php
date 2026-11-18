<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_subject_tasks', function (Blueprint $table) {
            $table->foreignUuid('task_id')->primary()->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('unit_id')->constrained('learning_units')->restrictOnDelete();
            $table->index(['user_id', 'unit_id'], 'learning_tasks_owner_unit');
        });

        Schema::create('learning_gradebooks', function (Blueprint $table) {
            $table->foreignUuid('unit_id')->primary()->constrained('learning_units')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('mode', 10);
            $table->integer('minimum');
            $table->integer('maximum');
            $table->integer('passing');
        });

        Schema::create('learning_grade_cuts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('unit_id')->constrained('learning_units')->cascadeOnDelete();
            $table->string('title', 160);
            $table->unsignedSmallInteger('weight');
            $table->index(['user_id', 'unit_id'], 'learning_cuts_owner_unit');
        });

        Schema::create('learning_evaluations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('unit_id')->constrained('learning_units')->restrictOnDelete();
            $table->foreignUuid('cut_id')->nullable()->constrained('learning_grade_cuts')->restrictOnDelete();
            $table->foreignUuid('task_id')->nullable()->unique()->constrained('tasks')->nullOnDelete();
            $table->string('title', 255);
            $table->string('kind', 12);
            $table->unsignedSmallInteger('weight')->nullable();
            $table->integer('score')->nullable();
            $table->date('scheduled_on')->nullable();
            $table->time('scheduled_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'unit_id'], 'learning_evals_owner_unit');
            $table->index(['user_id', 'scheduled_on'], 'learning_evals_owner_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_evaluations');
        Schema::dropIfExists('learning_grade_cuts');
        Schema::dropIfExists('learning_gradebooks');
        Schema::dropIfExists('learning_subject_tasks');
    }
};
