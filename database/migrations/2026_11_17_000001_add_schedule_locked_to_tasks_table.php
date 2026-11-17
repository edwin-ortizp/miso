<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // CHG-144: «Horario fijo». Una tarea con horario fijo (p. ej. una reunión) no se mueve con atajos ni al reorganizar.
            $table->boolean('schedule_locked')->default(false)->after('estimated_time');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('schedule_locked');
        });
    }
};
