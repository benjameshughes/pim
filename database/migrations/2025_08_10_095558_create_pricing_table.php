<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 🎭💰 PRICING TABLE - THE FINANCIAL FOUNDATION OF OUR EMPIRE! 💰🎭
     *
     * This table is SERVING major financial energy with multi-channel pricing,
     * cost tracking, profit margins, and discount support! ✨
     */
    public function up(): void
    {
        Schema::create('pricing', function (Blueprint $table) {
            $table->id();

            // 🏠 CORE RELATIONSHIPS
            $table->foreignId('product_variant_id')->constrained()->onDelete('cascade');
            $table->foreignId('sales_channel_id')->nullable()->constrained()->onDelete('cascade');

            // 💰 BASE PRICING (The Foundation)
            $table->decimal('cost_price', 10, 2)->default(0)->comment('What we pay supplier');
            $table->decimal('base_price', 10, 2)->comment('Our standard retail price');
            $table->string('currency', 3)->default('GBP');

            // 🎯 CHANNEL-SPECIFIC PRICING
            $table->decimal('channel_price', 10, 2)->nullable()->comment('Override for this channel');
            $table->decimal('markup_percentage', 5, 2)->nullable()->comment('Channel markup %');

            // 💸 DISCOUNT & SALES
            $table->decimal('sale_price', 10, 2)->nullable()->comment('Discounted price');
            $table->decimal('discount_percentage', 5, 2)->nullable()->comment('Discount % off base');
            $table->decimal('discount_amount', 10, 2)->nullable()->comment('Fixed discount amount');
            $table->datetime('sale_starts_at')->nullable();
            $table->datetime('sale_ends_at')->nullable();

            // 🚚 COSTS & FEES
            $table->decimal('shipping_cost', 8, 2)->default(0)->comment('Shipping cost to customer');
            $table->decimal('platform_fee_percentage', 5, 2)->default(0)->comment('Platform fee % (eBay, etc)');
            $table->decimal('payment_fee_percentage', 5, 2)->default(2.9)->comment('Payment processing fee');
            $table->decimal('vat_rate', 5, 2)->default(20.0)->comment('VAT/Tax rate %');
            $table->boolean('vat_inclusive')->default(true)->comment('Is VAT included in price?');

            // 📊 CALCULATED FIELDS (Updated automatically)
            $table->decimal('profit_amount', 10, 2)->nullable()->comment('Calculated profit');
            $table->decimal('profit_margin', 5, 2)->nullable()->comment('Profit margin %');
            $table->decimal('roi_percentage', 5, 2)->nullable()->comment('Return on investment %');

            // 🎪 METADATA & STATUS
            $table->boolean('is_active')->default(true);
            $table->string('status')->default('active'); // active, inactive, sale, discontinued
            $table->json('metadata')->nullable()->comment('Additional pricing data');
            $table->text('notes')->nullable();

            $table->timestamps();

            // 🎯 INDEXES FOR PERFORMANCE
            $table->index(['product_variant_id', 'sales_channel_id'], 'variant_channel_index');
            $table->index(['is_active', 'status'], 'active_status_index');
            $table->index(['sale_starts_at', 'sale_ends_at'], 'sale_period_index');
            $table->index('currency');
        });
    }

    /**
     * 💥 REVERSE THE MIGRATION
     */
    public function down(): void
    {
        Schema::dropIfExists('pricing');
    }
};
