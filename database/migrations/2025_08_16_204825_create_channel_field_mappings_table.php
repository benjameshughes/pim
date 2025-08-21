<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_field_mappings', function (Blueprint $table) {
            $table->id();

            // Channel identification (same as sync_accounts)
            $table->foreignId('sync_account_id')->constrained()->onDelete('cascade');

            // Target field identification
            $table->string('channel_field_code'); // The marketplace field
            $table->string('category')->nullable(); // Category context if applicable

            // Source mapping (what PIM data to use)
            $table->string('mapping_type'); // 'pim_field', 'static_value', 'expression', 'custom'
            $table->string('source_field')->nullable(); // PIM field name (product.name, variant.color, etc.)
            $table->text('static_value')->nullable(); // Fixed value
            $table->text('mapping_expression')->nullable(); // Complex mapping logic
            $table->json('transformation_rules')->nullable(); // Data transformation rules

            // Mapping metadata
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0); // For ordering in UI
            $table->text('notes')->nullable(); // User notes

            // Override levels
            $table->string('mapping_level')->default('global'); // 'global', 'product', 'variant'
            $table->foreignId('product_id')->nullable()->constrained()->onDelete('cascade'); // Product-specific override
            $table->string('variant_scope')->nullable(); // 'all', 'color:Red', 'size:Large', etc.

            // Validation and testing
            $table->json('validation_status')->nullable(); // Last validation result
            $table->timestamp('last_validated_at')->nullable();
            $table->json('test_results')->nullable(); // Mapping test results

            $table->timestamps();

            // Indexes
            $table->index(['sync_account_id', 'channel_field_code']);
            $table->index(['mapping_type', 'source_field']);
            $table->index(['mapping_level', 'product_id']);
            $table->index(['is_active', 'priority']);

            // Unique constraint for global mappings
            $table->unique(['sync_account_id', 'channel_field_code', 'category', 'mapping_level', 'product_id', 'variant_scope'], 'channel_mapping_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_field_mappings');
    }
};
