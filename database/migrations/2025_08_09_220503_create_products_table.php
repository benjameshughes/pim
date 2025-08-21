<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 🔥 PRODUCTS TABLE - THE PHOENIX HEART
     *
     * Simple, elegant, focused on what matters:
     * Product families like "Blackout Roller Blind"
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // Core product identity
            $table->string('name'); // "Blackout Roller Blind"
            $table->string('parent_sku')->unique(); // "026"
            $table->text('description')->nullable(); // Full product description
            $table->string('status')->default('active'); // active, inactive

            // Simple product image - single main image
            $table->string('image_url')->nullable();

            $table->timestamps();

            // Indexes for performance
            $table->index('status');
            $table->index('parent_sku');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
