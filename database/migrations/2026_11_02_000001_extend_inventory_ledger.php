<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meal_inventory_operations', function (Blueprint $table) {
            $table->string('document_type', 40)->nullable();
            $table->string('channel', 20)->nullable();
            $table->string('reason', 1000)->nullable();
            $table->json('origin')->nullable();
            $table->date('effective_date')->nullable();
            $table->dateTime('effective_at')->nullable();
            $table->json('document_lines')->nullable();
            $table->string('document_fingerprint', 64)->nullable();
            $table->foreignUuid('corrects_operation_id')->nullable()->constrained('meal_inventory_operations')->restrictOnDelete();
            $table->index(['user_id', 'document_type', 'created_at'], 'inventory_document_history');
        });

        Schema::table('meal_inventory_movements', function (Blueprint $table) {
            // Historical identity survives detachment of the live product during a merge.
            $table->uuid('historical_product_id')->nullable();
            $table->uuid('segment_id')->nullable();
            $table->unsignedBigInteger('sequence')->nullable();
            $table->string('opening_marker', 40)->nullable();
            $table->string('movement_type', 40)->nullable();
            $table->decimal('balance_before', 12, 3)->nullable();
            $table->decimal('balance_after', 12, 3)->nullable();
            $table->foreignUuid('compensates_movement_id')->nullable()->constrained('meal_inventory_movements')->restrictOnDelete();
            $table->unique(['user_id', 'historical_product_id', 'sequence'], 'inventory_product_sequence');
            $table->unique(['user_id', 'historical_product_id', 'opening_marker'], 'inventory_product_opening');
        });

        Schema::create('inventory_product_successors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Both identities must survive deletion of merged products, including intermediates.
            $table->uuid('source_product_id');
            $table->uuid('target_product_id');
            $table->foreignUuid('operation_id')->constrained('meal_inventory_operations')->restrictOnDelete();
            $table->string('unit', 10);
            $table->timestamps();
            $table->unique(['user_id', 'source_product_id'], 'inventory_successor_source');
            $table->index(['user_id', 'target_product_id'], 'inventory_successor_target');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_product_successors');
        // InnoDB may replace its implicit user FK index with either new composite index.
        // Restore a standalone support index before removing both composites.
        if (! Schema::hasIndex('meal_inventory_movements', ['user_id'])) {
            Schema::table('meal_inventory_movements', function (Blueprint $table) {
                $table->index('user_id', 'inventory_user_fk_support');
            });
        }
        Schema::table('meal_inventory_movements', function (Blueprint $table) {
            $table->dropForeign(['compensates_movement_id']);
            $table->dropUnique('inventory_product_sequence');
            $table->dropUnique('inventory_product_opening');
            $table->dropColumn(['historical_product_id', 'segment_id', 'sequence', 'opening_marker', 'movement_type', 'balance_before', 'balance_after', 'compensates_movement_id']);
        });
        Schema::table('meal_inventory_operations', function (Blueprint $table) {
            $table->dropForeign(['corrects_operation_id']);
            $table->dropIndex('inventory_document_history');
            $table->dropColumn(['document_type', 'channel', 'reason', 'origin', 'effective_date', 'effective_at', 'document_lines', 'document_fingerprint', 'corrects_operation_id']);
        });
    }
};
