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
        // MySQL may reuse pomodoro_task_date_index for the user_id foreign key
        // and drop the original implicit index; restore one before removing it.
        if (! Schema::hasIndex('pomodoro_sessions', ['user_id'])) {
            Schema::table('pomodoro_sessions', function (Blueprint $table): void {
                $table->index('user_id', 'pomodoro_sessions_user_id_foreign');
            });
        }

        Schema::table('pomodoro_sessions', function (Blueprint $table): void {
            $table->dropForeign(['task_id']);
            $table->dropIndex('pomodoro_task_date_index');
            $table->dropColumn(['task_id', 'task_title']);
        });
    }
};
