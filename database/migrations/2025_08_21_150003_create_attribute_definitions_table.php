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

            // Core definition
            $table->string('key')->unique(); // "brand", "material", "color"
            $table->string('name'); // "Brand", "Material", "Color"
            $table->text('description')->nullable();
            $table->string('data_type'); // "string", "number", "boolean", "enum", "json", "date", "url"

            // Validation and constraints
            $table->json('validation_rules')->nullable();
            $table->json('enum_values')->nullable(); // For enum types
            $table->string('default_value')->nullable();

            // Inheritance configuration
            $table->boolean('is_inheritable')->default(true);
            $table->string('inheritance_strategy')->default('fallback'); // "always", "fallback", "never"

            // Requirements
            $table->boolean('is_required_for_products')->default(false);
            $table->boolean('is_required_for_variants')->default(false);
            $table->boolean('is_unique_per_product')->default(false);
            $table->boolean('is_system_attribute')->default(false);

            // Marketplace sync
            $table->json('marketplace_mappings')->nullable();
            $table->boolean('sync_to_shopify')->default(false);
            $table->boolean('sync_to_ebay')->default(false);
            $table->boolean('sync_to_mirakl')->default(false);

            // UI configuration
            $table->string('input_type')->default('text');
            $table->json('ui_options')->nullable();
            $table->integer('sort_order')->default(0);
            $table->string('group')->nullable();
            $table->string('icon')->nullable();

            // Status
            $table->boolean('is_active')->default(true);
            $table->timestamp('deprecated_at')->nullable();
            $table->string('replaced_by')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('data_type');
            $table->index('group');
            $table->index('is_active');
            $table->index('is_system_attribute');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_definitions');
    }
};
