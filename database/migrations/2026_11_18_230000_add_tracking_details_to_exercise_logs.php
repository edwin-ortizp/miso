<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exercise_logs', function (Blueprint $table) {
            $table->unsignedInteger('total_reps')->nullable();
            $table->string('calories_source', 24)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('exercise_logs', function (Blueprint $table) {
            $table->dropColumn(['total_reps', 'calories_source']);
        });
    }
};
