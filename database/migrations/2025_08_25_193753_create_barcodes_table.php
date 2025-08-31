<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Simple Barcode System - Decoupled from Products
     */
    public function up(): void
    {
        Schema::create('barcodes', function (Blueprint $table) {
            $table->id();

            // Simple barcode data
            $table->string('barcode', 50)->unique(); // The actual barcode value
            $table->string('sku', 50)->nullable(); // SKU this barcode is linked to
            $table->text('title')->nullable(); // Title/description for legacy data
            $table->foreignId('product_variant_id')->nullable()->constrained()->onDelete('set null');
            $table->boolean('is_assigned'); // Whether this barcode is assigned to a SKU

            $table->timestamps();

            // Indexes
            $table->index('sku');
            $table->index('is_assigned');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('barcodes');
    }
};
