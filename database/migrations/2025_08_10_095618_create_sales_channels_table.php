<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 🛍️✨ SALES CHANNELS - WHERE THE MAGIC HAPPENS! ✨🛍️
     *
     * Every diva needs multiple stages to perform on!
     * Shopify, eBay, Amazon, Direct Sales - we serve them ALL! 💅
     */
    public function up(): void
    {
        Schema::create('sales_channels', function (Blueprint $table) {
            $table->id();

            // 🎪 CHANNEL IDENTITY
            $table->string('name')->unique(); // 'shopify', 'ebay', 'amazon', 'direct'
            $table->string('display_name'); // 'Shopify Store', 'eBay Marketplace'
            $table->string('slug')->unique();

            // 🎨 VISUAL IDENTITY
            $table->string('icon')->nullable(); // Icon name for UI
            $table->string('color')->default('#3B82F6'); // Brand color
            $table->text('description')->nullable();

            // 💰 DEFAULT PRICING RULES
            $table->decimal('default_markup_percentage', 5, 2)->default(25.0);
            $table->decimal('platform_fee_percentage', 5, 2)->default(0);
            $table->decimal('payment_fee_percentage', 5, 2)->default(2.9);
            $table->string('default_currency', 3)->default('GBP');

            // 🚚 SHIPPING & FULFILLMENT
            $table->decimal('base_shipping_cost', 8, 2)->default(0);
            $table->boolean('free_shipping_available')->default(false);
            $table->decimal('free_shipping_threshold', 10, 2)->nullable();

            // 🎯 CHANNEL SETTINGS
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_sync')->default(false); // Auto-sync pricing changes
            $table->integer('priority')->default(100); // Display order

            // 🔗 INTEGRATION DATA
            $table->json('api_credentials')->nullable(); // Encrypted API keys
            $table->json('settings')->nullable(); // Channel-specific settings
            $table->json('metadata')->nullable();

            $table->timestamps();

            // 🎯 INDEXES
            $table->index('is_active');
            $table->index('priority');
        });
    }

    /**
     * 💥 REVERSE THE MIGRATION
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_channels');
    }
};
