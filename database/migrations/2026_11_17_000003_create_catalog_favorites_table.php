<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** CHG-150: favoritos personales por catálogo e índices para calcular los más usados. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_favorites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('catalog_domain', 40);
            $table->string('item_id', 36);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'catalog_domain', 'item_id'], 'catalog_favorite_item');
            $table->index(['user_id', 'catalog_domain', 'position'], 'catalog_favorite_order');
        });

        Schema::table('drink_logs', fn (Blueprint $table) => $table->index(['user_id', 'drink_type_id', 'date'], 'drink_logs_usage'));
        Schema::table('exercise_logs', fn (Blueprint $table) => $table->index(['user_id', 'exercise_type_id', 'date'], 'exercise_logs_usage'));
    }

    public function down(): void
    {
        Schema::table('exercise_logs', fn (Blueprint $table) => $table->dropIndex('exercise_logs_usage'));
        Schema::table('drink_logs', fn (Blueprint $table) => $table->dropIndex('drink_logs_usage'));
        Schema::dropIfExists('catalog_favorites');
    }
};
