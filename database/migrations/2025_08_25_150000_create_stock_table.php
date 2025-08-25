<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 📦 INDEPENDENT STOCK TABLE
     * 
     * Stock operates independently from variants but references them
     */
    public function up(): void
    {
        Schema::create('stock', function (Blueprint $table) {
            $table->id();
            
            // Reference to variant (not ownership)
            $table->foreignId('product_variant_id')->constrained()->onDelete('cascade');
            
            // Stock data
            $table->integer('quantity'); // Current stock level
            $table->integer('reserved')->nullable(); // Reserved/allocated stock
            $table->integer('incoming')->nullable(); // Expected incoming stock
            $table->integer('minimum_level')->nullable(); // Low stock threshold
            $table->integer('maximum_level')->nullable(); // High stock threshold
            
            // Location/warehouse (optional)
            $table->string('location', 100)->nullable(); // Warehouse/location code
            $table->string('bin_location', 50)->nullable(); // Specific bin/shelf
            
            // Stock status
            $table->string('status', 30); // available, reserved, damaged, etc - default in code
            $table->boolean('track_stock'); // Whether to track stock for this variant - default in code
            
            // Timestamps
            $table->timestamp('last_counted_at')->nullable(); // Last physical count
            $table->text('notes')->nullable(); // Stock notes
            
            $table->timestamps();
            
            // Indexes
            $table->index(['product_variant_id', 'status']);
            $table->index(['status', 'track_stock']);
            $table->index('location');
            
            // Unique constraint: one stock record per variant per location
            $table->unique(['product_variant_id', 'location']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock');
    }
};