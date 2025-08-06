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
        Schema::create('product_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('attribute_key'); // max_drop, mechanism_type, material_type
            $table->text('attribute_value'); // 160cm, sidewinder, polyester
            $table->enum('data_type', ['string', 'number', 'boolean', 'json'])->default('string');
            $table->string('category')->nullable(); // physical, functional, compliance
            $table->timestamps();
            
            $table->unique(['product_id', 'attribute_key']);
            $table->index(['attribute_key', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_attributes');
    }
};
