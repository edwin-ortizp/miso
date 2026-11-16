<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cycle_day_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            // none, spotting, light, medium o heavy; sin cifrar para poder contar y ordenar.
            $table->string('flow', 10)->nullable();
            // Síntomas y notas cifrados con los casts `encrypted:array` y `encrypted` (CHG-130).
            $table->text('symptoms')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_sensitive')->default(true);
            $table->string('source', 40)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'date'], 'cd_user_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cycle_day_logs');
    }
};
