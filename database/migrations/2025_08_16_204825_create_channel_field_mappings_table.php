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
            $table->string('channel_field_code', 100); // The marketplace field
            $table->string('category', 100)->nullable(); // Category context if applicable

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
            $table->string('mapping_level', 50)->default('global'); // 'global', 'product', 'variant'
            $table->foreignId('product_id')->nullable()->constrained()->onDelete('cascade'); // Product-specific override
            $table->string('variant_scope', 100)->nullable(); // 'all', 'color:Red', 'size:Large', etc.

            // Validation and testing
            $table->json('validation_status')->nullable(); // Last validation result
            $table->timestamp('last_validated_at')->nullable();
            $table->json('test_results')->nullable(); // Mapping test results

            $table->timestamps();

            // Indexes with proper names
            $table->index(['sync_account_id', 'channel_field_code'], 'cfm_account_field_idx');
            $table->index(['mapping_type', 'source_field'], 'cfm_type_source_idx');
            $table->index(['mapping_level', 'product_id'], 'cfm_level_product_idx');
            $table->index(['is_active', 'priority'], 'cfm_active_priority_idx');

            // Simpler unique constraints to avoid MySQL key length issues
            $table->unique(['sync_account_id', 'channel_field_code', 'mapping_level'], 'cfm_basic_unique');
            $table->index(['category', 'product_id', 'variant_scope'], 'cfm_scope_idx'); // Index for additional uniqueness checking
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_field_mappings');
    }
};
