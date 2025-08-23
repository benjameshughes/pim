<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create variant_attributes table for variant-specific attribute values
     */
    public function up(): void
    {
        Schema::create('variant_attributes', function (Blueprint $table) {
            $table->id();
            
            // Relationships
            $table->foreignId('variant_id')->constrained('product_variants')->onDelete('cascade');
            $table->foreignId('attribute_definition_id')->constrained()->onDelete('cascade');
            
            // Value storage and display
            $table->text('value')->nullable();
            $table->text('display_value')->nullable();
            $table->json('value_metadata')->nullable();
            
            // Validation status
            $table->boolean('is_valid')->default(true);
            $table->json('validation_errors')->nullable();
            $table->timestamp('last_validated_at')->nullable();
            
            // Assignment tracking
            $table->string('source')->default('manual');
            $table->timestamp('assigned_at')->nullable();
            $table->string('assigned_by')->nullable();
            $table->json('assignment_metadata')->nullable();
            
            // Inheritance tracking
            $table->boolean('is_inherited')->default(false);
            $table->foreignId('inherited_from_product_attribute_id')->nullable()->constrained('product_attributes')->onDelete('set null');
            $table->timestamp('inherited_at')->nullable();
            $table->boolean('is_override')->default(false);
            
            // Marketplace sync status
            $table->json('sync_status')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('sync_metadata')->nullable();
            
            // Change tracking
            $table->text('previous_value')->nullable();
            $table->timestamp('value_changed_at')->nullable();
            $table->integer('version')->default(1);
            
            $table->timestamps();
            
            // Unique constraint - one value per variant per attribute
            $table->unique(['variant_id', 'attribute_definition_id']);
            
            // Indexes
            $table->index('source');
            $table->index('is_valid');
            $table->index('is_inherited');
            $table->index('is_override');
            $table->index('last_validated_at');
            $table->index('value_changed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variant_attributes');
    }
};