<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('android_installations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('secret_hash', 64);
            $t->text('token');
            $t->string('token_hash', 64)->unique();
            $t->uuid('binding');
            $t->boolean('active')->default(true);
            $t->timestamp('activated_at');
            $t->timestamps();
            $t->index(['user_id', 'active']);
        });
        Schema::create('android_deliveries', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('installation_id')->constrained('android_installations')->cascadeOnDelete();
            $t->foreignUuid('notification_id')->constrained('notifications')->cascadeOnDelete();
            $t->uuid('binding');
            $t->string('status', 20)->default('pending');
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->timestamp('next_attempt_at')->nullable();
            $t->timestamp('claimed_at')->nullable();
            $t->timestamp('accepted_at')->nullable();
            $t->timestamps();
            $t->unique(['installation_id', 'notification_id', 'binding'], 'android_delivery_unique');
            $t->index(['status', 'next_attempt_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('android_deliveries');
        Schema::dropIfExists('android_installations');
    }
};
