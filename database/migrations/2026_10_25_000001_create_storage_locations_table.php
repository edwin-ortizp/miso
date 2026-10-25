<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storage_locations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('parent_id')->nullable();
            $table->string('name');
            $table->string('normalized_name');
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(['id', 'user_id']);
            $table->index(['user_id', 'parent_id', 'normalized_name']);
            $table->foreign(['parent_id', 'user_id'])->references(['id', 'user_id'])->on('storage_locations')->restrictOnDelete();
        });
        Schema::table('shopping_items', function (Blueprint $table) {
            $table->uuid('storage_location_id')->nullable()->after('category_id');
            $table->foreign(['storage_location_id', 'user_id'])->references(['id', 'user_id'])->on('storage_locations')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shopping_items', function (Blueprint $table) {
            $table->dropForeign(['storage_location_id', 'user_id']);
            $table->dropColumn('storage_location_id');
        });
        Schema::dropIfExists('storage_locations');
    }
};
