<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source_type', 100);
            $table->uuid('source_id');
            $table->string('type', 40);
            $table->string('occurrence_key', 100);
            $table->string('title');
            $table->text('body');
            $table->dateTime('scheduled_for');
            $table->dateTime('read_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->dateTime('telegram_sent_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'source_type', 'source_id', 'type', 'occurrence_key'], 'notifications_occurrence_unique');
            $table->index(['user_id', 'scheduled_for', 'read_at']);
        });
        Schema::create('reminders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source_type', 100);
            $table->uuid('source_id');
            $table->unsignedInteger('amount');
            $table->string('unit', 10);
            $table->timestamps();
            $table->unique(['user_id', 'source_type', 'source_id', 'amount', 'unit'], 'reminders_anticipation_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
        Schema::dropIfExists('notifications');
    }
};
