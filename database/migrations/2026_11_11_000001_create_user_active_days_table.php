<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Días activos por usuario y canal de entrada (CHG-067): línea base de recurrencia. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_active_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('first_channel', 20)->default('direct');
            $table->unsignedInteger('records_count')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_active_days');
    }
};
