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
        Schema::create('marketplaces', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // "eBay Business Outlet", "Amazon FBA"
            $table->string('platform'); // "ebay", "amazon", "wayfair", "direct"
            $table->string('code')->unique(); // "ebo", "amazon_fba", "wayfair"
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->json('default_settings')->nullable(); // Platform-specific defaults
            $table->timestamps();

            $table->index(['platform', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketplaces');
    }
};
