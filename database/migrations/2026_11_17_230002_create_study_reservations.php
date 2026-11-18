<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('study_reservations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('formation_id')->nullable()->constrained('learning_formations')->nullOnDelete();
            $table->foreignUuid('unit_id')->nullable()->constrained('learning_units')->nullOnDelete();
            $table->string('title', 160);
            $table->date('date');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('frequency', 8)->default('none');
            $table->unsignedTinyInteger('interval')->default(1);
            $table->json('weekdays');
            $table->date('until')->nullable();
            $table->json('task_ids');
            $table->timestamps();
            $table->index(['user_id', 'date']);
        });
        Schema::create('study_reservation_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('reservation_id')->constrained('study_reservations')->cascadeOnDelete();
            $table->date('original_date');
            $table->date('date')->nullable()->index();
            $table->json('changes')->nullable();
            $table->unique(['reservation_id', 'original_date'], 'study_exception_occurrence_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_reservation_exceptions');
        Schema::dropIfExists('study_reservations');
    }
};
