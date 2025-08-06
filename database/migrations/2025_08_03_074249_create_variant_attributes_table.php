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
        Schema::create('variant_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')->constrained('product_variants')->onDelete('cascade');
            $table->string('attribute_key'); // fabric_width_difference, opacity_level, fire_rating
            $table->text('attribute_value'); // 4cm, 100%, Class 1
            $table->enum('data_type', ['string', 'number', 'boolean', 'json'])->default('string');
            $table->string('category')->nullable(); // physical, functional, compliance
            $table->timestamps();
            
            $table->unique(['variant_id', 'attribute_key']);
            $table->index(['attribute_key', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('variant_attributes');
    }
};
