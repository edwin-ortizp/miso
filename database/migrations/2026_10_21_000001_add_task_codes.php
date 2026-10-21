<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_categories', function (Blueprint $table) {
            $table->string('code_prefix', 5)->nullable();
            $table->unique(['user_id', 'code_prefix']);
        });
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('code', 32)->nullable();
            $table->unique(['user_id', 'code']);
        });
        Schema::create('task_code_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('prefix', 5);
            $table->unsignedBigInteger('last_number')->default(0);
            $table->unique(['user_id', 'prefix']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_code_counters');
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'code']);
            $table->dropColumn('code');
        });
        Schema::table('task_categories', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'code_prefix']);
            $table->dropColumn('code_prefix');
        });
    }
};
