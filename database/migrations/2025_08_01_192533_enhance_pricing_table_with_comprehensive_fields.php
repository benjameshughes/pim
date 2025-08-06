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
        Schema::table('pricing', function (Blueprint $table) {
            // Add comprehensive pricing fields
            $table->decimal('cost_price', 10, 2)->nullable()->after('retail_price');
            $table->decimal('vat_percentage', 5, 2)->default(20.00)->after('cost_price');
            $table->decimal('vat_amount', 10, 2)->nullable()->after('vat_percentage');
            $table->decimal('channel_fee_percentage', 5, 2)->default(0.00)->after('shipping_cost');
            $table->decimal('channel_fee_amount', 10, 2)->nullable()->after('channel_fee_percentage');
            $table->decimal('profit_amount', 10, 2)->nullable()->after('channel_fee_amount');
            $table->decimal('profit_margin_percentage', 5, 2)->nullable()->after('profit_amount');
            $table->string('currency', 3)->default('GBP')->after('marketplace');
            $table->boolean('vat_inclusive')->default(true)->after('vat_amount');
            
            // Rename existing columns for clarity
            $table->renameColumn('vat_cost', 'total_cost');
            $table->renameColumn('net_cost', 'final_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pricing', function (Blueprint $table) {
            $table->dropColumn([
                'cost_price',
                'vat_percentage', 
                'vat_amount',
                'channel_fee_percentage',
                'channel_fee_amount',
                'profit_amount',
                'profit_margin_percentage',
                'currency',
                'vat_inclusive',
            ]);
            
            $table->renameColumn('total_cost', 'vat_cost');
            $table->renameColumn('final_price', 'net_cost');
        });
    }
};
