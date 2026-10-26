<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['task_categories', 'task_statuses'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->string('color', 16)->nullable());
        }
    }

    public function down(): void
    {
        foreach (['task_categories', 'task_statuses'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('color'));
        }
    }
};
