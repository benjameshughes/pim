<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Essential Products Table - Clean & Simple
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            
            // Core product identity
            $table->string('name'); // "Blackout Roller Blind"
            $table->string('parent_sku', 50)->unique(); // "026"  
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active'); // active, inactive
            
            // Simple product image
            $table->string('image_url')->nullable();
            
            $table->timestamps();
            
            // Simple indexes
            $table->index('status');
            $table->index('parent_sku');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};