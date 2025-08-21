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
            
            // Core definition
            $table->string('key')->unique(); // e.g. 'brand', 'material', 'color'
            $table->string('name'); // Display name: 'Brand', 'Material', 'Color'
            $table->text('description')->nullable(); // Help text for users
            
            // Data type and validation
            $table->enum('data_type', ['string', 'number', 'boolean', 'enum', 'json', 'date', 'url'])->default('string');
            $table->json('validation_rules')->nullable(); // Laravel validation rules
            $table->json('enum_values')->nullable(); // For enum types: ['Red', 'Blue', 'Green']
            $table->string('default_value')->nullable(); // Default value if not set
            
            // Inheritance and hierarchy
            $table->boolean('is_inheritable')->default(true); // Can variants inherit from products?
            $table->enum('inheritance_strategy', ['always', 'fallback', 'never'])->default('fallback');
            // 'always' = variants always get parent value
            // 'fallback' = variants use parent value if no variant value set
            // 'never' = no inheritance
            
            // Requirements and constraints
            $table->boolean('is_required_for_products')->default(false);
            $table->boolean('is_required_for_variants')->default(false);
            $table->boolean('is_unique_per_product')->default(false); // Must be unique across variants of a product
            $table->boolean('is_system_attribute')->default(false); // Core system attributes like 'brand'
            
            // Marketplace integration
            $table->json('marketplace_mappings')->nullable(); // How this maps to different marketplaces
            $table->boolean('sync_to_shopify')->default(false);
            $table->boolean('sync_to_ebay')->default(false);
            $table->boolean('sync_to_mirakl')->default(false);
            
            // UI and display
            $table->string('input_type')->default('text'); // text, textarea, select, checkbox, etc.
            $table->json('ui_options')->nullable(); // UI-specific options
            $table->integer('sort_order')->default(0); // Display order in forms
            $table->string('group')->nullable(); // Group attributes together: 'basic', 'physical', 'marketing'
            $table->string('icon')->nullable(); // Icon for UI
            
            // Status and metadata
            $table->boolean('is_active')->default(true);
            $table->timestamp('deprecated_at')->nullable(); // When this attribute was deprecated
            $table->string('replaced_by')->nullable(); // If deprecated, what replaced it
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['is_active', 'sort_order']);
            $table->index(['group', 'sort_order']);
            $table->index(['data_type']);
            $table->index(['is_inheritable']);
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