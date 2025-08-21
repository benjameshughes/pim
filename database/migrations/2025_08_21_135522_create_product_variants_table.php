<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Essential Product Variants Table - Clean & Simple  
     */
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            
            // Product relationship
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            
            // Variant identity
            $table->string('sku', 100)->unique(); // "026-001"
            $table->string('external_sku', 100)->nullable(); // "BOAU060"
            $table->string('title'); // "Blackout Roller Blind Aubergine 60cm"
            
            // Core attributes (your main use case)
            $table->string('color', 100); // "Aubergine", "Black" 
            $table->integer('width'); // 60, 90, 120cm etc
            $table->integer('drop')->nullable(); // Customer specified
            $table->integer('max_drop')->default(160); // Maximum allowed
            
            // Business essentials
            $table->decimal('price', 10, 2);
            $table->integer('stock_level')->default(0);
            $table->string('status', 20)->default('active');
            
            // Shipping dimensions
            $table->decimal('parcel_length', 8, 2)->nullable();
            $table->decimal('parcel_width', 8, 2)->nullable(); 
            $table->decimal('parcel_depth', 8, 2)->nullable();
            $table->decimal('parcel_weight', 8, 3)->nullable();
            
            $table->timestamps();
            
            // Essential indexes only
            $table->index('product_id');
            $table->index('status'); 
            $table->index('color');
            $table->index('width');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};