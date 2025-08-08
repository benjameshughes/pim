<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('deleted_product_variants', function (Blueprint $table) {
            $table->id();

            // Original references
            $table->unsignedBigInteger('original_product_id');
            $table->unsignedBigInteger('original_variant_id');

            // Product context (parent/child relationship preserved)
            $table->string('product_name');
            $table->string('product_parent_sku', 10)->nullable(); // null = was a parent, value = was a child
            $table->text('product_description')->nullable();

            // Variant essentials
            $table->string('variant_sku');
            $table->string('color', 100)->nullable();
            $table->string('width', 100)->nullable();
            $table->string('drop', 100)->nullable();

            // Barcode tracking
            $table->string('primary_barcode', 20)->nullable();
            $table->string('barcode_type', 20)->nullable();

            // Context at deletion
            $table->integer('stock_level')->default(0);
            $table->string('status', 20)->nullable();

            // Audit trail
            $table->timestamp('deleted_at');
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->enum('deletion_reason', [
                'discontinued',
                'duplicate',
                'data_error',
                'customer_request',
                'inventory_cleanup',
                'other',
            ]);
            $table->text('deletion_notes')->nullable();

            // Indexes for common queries
            $table->index('variant_sku');
            $table->index('product_name');
            $table->index('primary_barcode');
            $table->index('deleted_at');
            $table->index('original_product_id');
            $table->index('deletion_reason');
            $table->index(['original_product_id', 'original_variant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deleted_product_variants');
    }
};
