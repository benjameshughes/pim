<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create attribute_definitions table for flexible attributes system
     */
    public function up(): void
    {
        Schema::create('attribute_definitions', function (Blueprint $table) {
            $table->id();
            
            // Attribute definition
            $table->string('name'); // "brand", "material", "color"
            $table->string('label'); // "Brand", "Material", "Color"
            $table->string('type'); // "text", "number", "boolean", "select", "multi_select"
            $table->text('description')->nullable();
            
            // Validation rules
            $table->boolean('is_required')->default(false);
            $table->boolean('is_unique')->default(false);
            $table->json('validation_rules')->nullable(); // Laravel validation rules
            
            // Options for select/multi_select types
            $table->json('options')->nullable(); // ["Red", "Blue", "Green"]
            
            // Organization
            $table->string('group')->nullable(); // "basic", "advanced", "marketplace"
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            
            // Inheritance rules
            $table->boolean('is_inheritable')->default(true); // Can variants inherit this?
            
            // Marketplace mapping
            $table->json('marketplace_mappings')->nullable(); // Map to marketplace fields
            
            $table->timestamps();
            
            // Indexes
            $table->unique('name');
            $table->index('type');
            $table->index('group');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_definitions');
    }
};