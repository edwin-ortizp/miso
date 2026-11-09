<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pomodoro_sessions', function (Blueprint $table): void {
            $table->uuid('task_id')->nullable();
            $table->string('task_title', 255)->nullable();
            $table->foreign('task_id')->references('id')->on('tasks')->nullOnDelete();
            $table->index(['user_id', 'task_id', 'date'], 'pomodoro_task_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('pomodoro_sessions', function (Blueprint $table): void {
            $table->dropForeign(['task_id']);
            $table->dropIndex('pomodoro_task_date_index');
            $table->dropColumn(['task_id', 'task_title']);
        });
    }
};
