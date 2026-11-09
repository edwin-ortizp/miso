<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_missions', function (Blueprint $table): void {
            $table->string('source', 20)->default('auto');
        });
    }

    public function down(): void
    {
        Schema::table('game_missions', fn (Blueprint $table) => $table->dropColumn('source'));
    }
};
