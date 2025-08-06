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
        Schema::create('marketplace_barcodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')->constrained('product_variants')->onDelete('cascade');
            $table->foreignId('marketplace_id')->constrained()->onDelete('cascade');
            $table->enum('identifier_type', ['sku', 'asin', 'item_id', 'listing_id', 'product_id']);
            $table->string('identifier_value'); // The actual ASIN, item ID, etc.
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['variant_id', 'marketplace_id', 'identifier_type']);
            $table->index(['marketplace_id', 'identifier_type', 'is_active']);
            $table->index('identifier_value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketplace_barcodes');
    }
};
