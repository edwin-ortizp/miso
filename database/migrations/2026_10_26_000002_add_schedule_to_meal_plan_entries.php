<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('meal_plan_entries', function (Blueprint $table) {
            $table->time('scheduled_time')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('meal_plan_entries', fn (Blueprint $table) => $table->dropColumn(['scheduled_time', 'duration_minutes']));
    }
};
