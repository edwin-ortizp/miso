<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_formations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->string('status', 20)->default('in_progress');
            $table->string('institution')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status'], 'learning_user_status');
        });
        Schema::create('learning_units', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('formation_id')->constrained('learning_formations')->restrictOnDelete();
            $table->foreignUuid('parent_id')->nullable()->constrained('learning_units')->restrictOnDelete();
            $table->string('kind', 20);
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('pending');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('credits', 4, 1)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['formation_id', 'parent_id', 'position'], 'learning_unit_order');
        });
        Schema::create('learning_shortcuts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('target_type', 12);
            $table->uuid('target_id');
            $table->unsignedInteger('position');
            $table->timestamps();
            $table->unique(['user_id', 'target_type', 'target_id'], 'learning_shortcut_target');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_shortcuts');
        Schema::dropIfExists('learning_units');
        Schema::dropIfExists('learning_formations');
    }
};
