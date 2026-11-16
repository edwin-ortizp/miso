<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medication_intakes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Null en una toma puntual o si el medicamento se eliminó: la toma conserva su nombre.
            $table->uuid('medication_id')->nullable();
            $table->string('name', 120);
            $table->decimal('dose_amount', 8, 2)->nullable();
            $table->string('dose_unit', 20)->nullable();
            // Hora esperada de la toma programada; null en las tomas puntuales.
            $table->dateTime('scheduled_for')->nullable();
            $table->dateTime('taken_at')->nullable();
            $table->string('status', 10);
            $table->string('skip_reason')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_sensitive')->default(false);
            $table->string('source', 40)->nullable();
            // Clave estable de reintento (MCP): repetir la misma operación no duplica la toma.
            $table->string('operation_key', 64)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'scheduled_for'], 'mi_user_sched_idx');
            $table->index(['user_id', 'taken_at'], 'mi_user_taken_idx');
            $table->unique(['medication_id', 'scheduled_for'], 'mi_med_sched_unique');
            $table->unique(['user_id', 'operation_key'], 'mi_user_opkey_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medication_intakes');
    }
};
