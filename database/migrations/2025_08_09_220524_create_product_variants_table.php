<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 💎 PRODUCT_VARIANTS TABLE - THE KILLER HEELS
     *
     * Where the magic happens - color, width, drop combinations
     * Each variant = a unique sellable item
     */
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();

            // Product relationship - the sacred connection
            $table->foreignId('product_id')->constrained()->onDelete('cascade');

            // Variant identity
            $table->string('sku')->unique(); // "026-001", "026-002"
            $table->string('external_sku')->nullable(); // "BOAU060", "BOBL090" (Linnworks)
            $table->string('title'); // "Blackout Roller Blind Aubergine 60cm"

            // THE KILLER ATTRIBUTES - Width & Drop separate!
            $table->string('color'); // "Aubergine", "Black", "Burnt Orange"
            $table->integer('width'); // 60, 90, 120, 150, 180, 210, 240 (cm)
            $table->integer('drop')->nullable(); // Customer specified, up to max (cm)
            $table->integer('max_drop')->default(160); // Maximum allowed drop (cm)

            // Business essentials
            $table->decimal('price', 10, 2); // Retail price
            $table->integer('stock_level')->default(0); // Current stock
            $table->string('status')->default('active'); // active, inactive

            // Shipping dimensions (from your CSV)
            $table->decimal('parcel_length', 8, 2)->nullable(); // cm
            $table->decimal('parcel_width', 8, 2)->nullable();  // cm
            $table->decimal('parcel_depth', 8, 2)->nullable();  // cm
            $table->decimal('parcel_weight', 8, 3)->nullable(); // kg

            $table->timestamps();

            // Indexes for performance and uniqueness
            $table->index('product_id');
            $table->index('status');
            $table->index('color');
            $table->index('width');
            $table->index(['product_id', 'color', 'width']); // Unique combo index

            // Business rule: One variant per product+color+width combination
            $table->unique(['product_id', 'color', 'width']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
