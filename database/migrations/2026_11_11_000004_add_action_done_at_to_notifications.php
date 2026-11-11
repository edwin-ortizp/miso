<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** CHG-069: una acción rápida desde el aviso (p. ej. +1 vaso) se ejecuta una sola vez. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->dateTime('action_done_at')->nullable()->after('push_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropColumn('action_done_at');
        });
    }
};
