<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->char('code', 2)->primary();
            $table->string('name');
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->char('country_code', 2);
            $table->foreign('country_code')->references('code')->on('countries');
            $table->string('name');
            $table->string('normalized_name');
            $table->string('region')->nullable();
            $table->unsignedBigInteger('population')->default(0);
            $table->index(['country_code', 'normalized_name']);
            $table->index('normalized_name');
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->char('country_code', 2)->nullable();
            $table->foreign('country_code')->references('code')->on('countries');
            $table->foreignId('city_id')->nullable()->constrained('cities');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropForeign(['country_code']);
            $table->dropForeign(['city_id']);
        });
        Schema::table('plans', fn (Blueprint $table) => $table->dropColumn(['country_code', 'city_id']));
        Schema::dropIfExists('cities');
        Schema::dropIfExists('countries');
    }
};
