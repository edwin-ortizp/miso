<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('friendships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('addressee_id')->constrained('users')->cascadeOnDelete();
            // Par normalizado (menor, mayor): impide una solicitud cruzada duplicada.
            $table->unsignedBigInteger('user_low_id');
            $table->unsignedBigInteger('user_high_id');
            $table->string('status', 12)->default('pending');
            $table->foreignId('blocked_by')->nullable()->constrained('users')->cascadeOnDelete();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['user_low_id', 'user_high_id'], 'fr_pair_unique');
            $table->index(['addressee_id', 'status'], 'fr_addressee_status');
            $table->index(['requester_id', 'status'], 'fr_requester_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('friendships');
    }
};
