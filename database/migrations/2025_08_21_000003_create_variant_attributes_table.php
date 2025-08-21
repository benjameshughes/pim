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
            
            // Core relationships
            $table->foreignId('variant_id')->constrained('product_variants')->onDelete('cascade');
            $table->foreignId('attribute_definition_id')->constrained()->onDelete('cascade');
            
            // Attribute value storage
            $table->text('value')->nullable(); // Flexible storage for any data type
            $table->string('display_value')->nullable(); // Formatted value for display
            $table->json('value_metadata')->nullable(); // Additional context about the value
            
            // Validation and quality
            $table->boolean('is_valid')->default(true); // Whether value passes validation
            $table->json('validation_errors')->nullable(); // Validation error details
            $table->timestamp('last_validated_at')->nullable(); // When validation was last run
            
            // Source and audit trail
            $table->string('source')->default('manual'); // manual, import, api, inheritance
            $table->timestamp('assigned_at')->nullable(); // When value was assigned
            $table->string('assigned_by')->nullable(); // Who/what assigned this value
            $table->json('assignment_metadata')->nullable(); // Additional assignment context
            
            // Inheritance tracking
            $table->boolean('is_inherited')->default(false); // Whether this value was inherited from product
            $table->foreignId('inherited_from_product_attribute_id')->nullable()->constrained('product_attributes')->onDelete('set null');
            $table->timestamp('inherited_at')->nullable(); // When value was inherited
            $table->boolean('is_override')->default(false); // Whether this overrides inherited value
            
            // Marketplace sync tracking
            $table->json('sync_status')->nullable(); // Status per marketplace
            $table->timestamp('last_synced_at')->nullable(); // When last synced to any marketplace
            $table->json('sync_metadata')->nullable(); // Marketplace-specific data
            
            // Change tracking
            $table->text('previous_value')->nullable(); // Previous value for change tracking
            $table->timestamp('value_changed_at')->nullable(); // When value was last changed
            $table->integer('version')->default(1); // Value version for history
            
            $table->timestamps();
            
            // Indexes for performance
            $table->unique(['variant_id', 'attribute_definition_id']); // One value per attribute per variant
            $table->index(['variant_id', 'is_valid']); // For getting valid attributes
            $table->index(['attribute_definition_id', 'value']); // For searching by attribute value
            $table->index(['source']); // For filtering by source
            $table->index(['is_inherited', 'inherited_at']); // For inheritance operations
            $table->index(['is_override']); // For finding overrides
            $table->index(['last_synced_at']); // For sync operations
            $table->index(['is_valid', 'last_validated_at']); // For validation operations
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