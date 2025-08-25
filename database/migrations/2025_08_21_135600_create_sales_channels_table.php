<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Essential Sales Channels Table - Simple Channel Management
     */
    public function up(): void
    {
        Schema::create('sales_channels', function (Blueprint $table) {
            $table->id();

            // Channel identity
            $table->string('name'); // "Shopify", "eBay", "Direct Sales"
            $table->string('code', 20)->unique(); // "shopify", "ebay", "direct"
            $table->text('description')->nullable();

            // Channel configuration
            $table->json('config')->nullable(); // API keys, settings, etc
            $table->string('status', 20)->default('active');

            $table->timestamps();

            // Indexes
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_channels');
    }
};
