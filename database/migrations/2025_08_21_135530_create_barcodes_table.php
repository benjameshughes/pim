<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Essential Barcodes Table - Clean & Simple
     */
    public function up(): void
    {
        Schema::create('barcodes', function (Blueprint $table) {
            $table->id();

            // Product variant relationship
            $table->foreignId('product_variant_id')->constrained()->onDelete('cascade');

            // Barcode data
            $table->string('barcode', 50)->unique(); // "1234567890123"
            $table->string('type', 20)->default('EAN13'); // EAN13, UPC, CODE128
            $table->string('status', 20)->default('active'); // active, inactive

            $table->timestamps();

            // Simple indexes
            $table->index('product_variant_id');
            $table->index('status');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barcodes');
    }
};
