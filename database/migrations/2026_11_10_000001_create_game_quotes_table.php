<?php

use App\Models\Game\GameQuote;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Frases del integrante del día (CHG-043). Las semilla (user_id null) van con la migración. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_quotes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('text', 140);
            $table->string('source', 20)->default('seed');
            $table->date('week_start')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'week_start']);
        });

        $now = now();
        DB::table('game_quotes')->insert(array_map(
            fn (string $text) => ['user_id' => null, 'text' => $text, 'source' => GameQuote::SOURCE_SEED, 'created_at' => $now, 'updated_at' => $now],
            GameQuote::SEED,
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('game_quotes');
    }
};
