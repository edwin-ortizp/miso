<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** CHG-071: una bienvenida por regreso; el único impide que reaparezca o se duplique. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('welcome_backs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('return_date');
            $table->unsignedSmallInteger('days_away');
            $table->string('outcome', 20)->default('dismissed');
            $table->timestamps();
            $table->unique(['user_id', 'return_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('welcome_backs');
    }
};
