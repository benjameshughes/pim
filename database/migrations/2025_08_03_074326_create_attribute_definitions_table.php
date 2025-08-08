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
        Schema::create('attribute_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // opacity_level, max_drop, mechanism_type
            $table->string('label'); // Display name: "Opacity Level", "Maximum Drop"
            $table->enum('data_type', ['string', 'number', 'boolean', 'json'])->default('string');
            $table->string('category')->nullable(); // physical, functional, compliance
            $table->enum('applies_to', ['product', 'variant', 'both'])->default('both');
            $table->boolean('is_required')->default(false);
            $table->json('validation_rules')->nullable(); // min/max values, allowed options
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['category', 'applies_to', 'is_active']);
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attribute_definitions');
    }
};
