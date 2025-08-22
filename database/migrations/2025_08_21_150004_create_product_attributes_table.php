<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create product_attributes table for product attribute values
     */
    public function up(): void
    {
        Schema::create('product_attributes', function (Blueprint $table) {
            $table->id();
            
            // Relationships
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('attribute_definition_id')->constrained()->onDelete('cascade');
            
            // Attribute value (stored as text, typed in application)
            $table->text('value')->nullable();
            $table->string('value_type')->default('string'); // "string", "number", "boolean", "array"
            
            // Data quality
            $table->string('source')->default('manual'); // "manual", "import", "api"
            $table->decimal('confidence_score', 3, 2)->default(1.00); // 0.00-1.00
            $table->json('validation_errors')->nullable(); // Track validation issues
            
            // Metadata
            $table->json('metadata')->nullable(); // Additional context
            $table->timestamp('verified_at')->nullable(); // When was this verified
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            
            $table->timestamps();
            
            // Unique constraint - one value per product per attribute
            $table->unique(['product_id', 'attribute_definition_id']);
            
            // Indexes
            $table->index('source');
            $table->index('confidence_score');
            $table->index('verified_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_attributes');
    }
};