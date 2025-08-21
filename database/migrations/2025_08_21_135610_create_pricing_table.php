<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Essential Pricing Table - Channel-Specific Pricing
     */
    public function up(): void
    {
        Schema::create('pricing', function (Blueprint $table) {
            $table->id();
            
            // Product relationship
            $table->foreignId('product_variant_id')->constrained()->onDelete('cascade');
            $table->foreignId('sales_channel_id')->constrained()->onDelete('cascade');
            
            // Pricing data
            $table->decimal('price', 10, 2); // £19.99
            $table->decimal('cost_price', 10, 2)->nullable(); // £8.50
            $table->decimal('discount_price', 10, 2)->nullable(); // £14.99
            
            // Pricing rules
            $table->decimal('margin_percentage', 5, 2)->nullable(); // 42.50%
            $table->string('currency', 3)->default('GBP');
            
            $table->timestamps();
            
            // Unique constraint - one price per variant per channel
            $table->unique(['product_variant_id', 'sales_channel_id'], 'pricing_variant_channel_unique');
            
            // Indexes
            $table->index('product_variant_id');
            $table->index('sales_channel_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing');
    }
};