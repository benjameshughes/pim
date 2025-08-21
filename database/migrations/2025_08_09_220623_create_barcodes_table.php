<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ✨ BARCODES TABLE - THE ESSENTIAL ACCESSORIES
     *
     * Your dual barcode system - Caecus + System barcodes
     * Each variant can have multiple barcodes
     */
    public function up(): void
    {
        Schema::create('barcodes', function (Blueprint $table) {
            $table->id();

            // Variant relationship - each barcode belongs to a variant
            $table->foreignId('product_variant_id')->constrained()->onDelete('cascade');

            // The barcode data (from your CSV structure)
            $table->string('barcode'); // The actual barcode number
            $table->string('type'); // 'caecus', 'system', 'ean13', 'upc' etc
            $table->string('status')->default('active'); // active, inactive, used

            $table->timestamps();

            // Indexes for performance
            $table->index('product_variant_id');
            $table->index('barcode');
            $table->index('type');
            $table->index('status');

            // Business rule: Each barcode should be unique
            $table->unique(['barcode', 'type']);
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
