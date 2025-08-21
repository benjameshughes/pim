<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ✨ BARCODE POOL TABLE - GS1 BARCODE MANAGEMENT SYSTEM
     *
     * Comprehensive barcode pool for managing large CSV imports with:
     * - Historical/legacy data preservation
     * - Smart assignment starting from row 40,000+
     * - Assignment tracking and quality management
     * - Performance optimization for 40k+ records
     */
    public function up(): void
    {
        Schema::create('barcode_pool', function (Blueprint $table) {
            $table->id();

            // Core barcode data
            $table->string('barcode')->unique(); // The actual GS1 barcode
            $table->string('barcode_type', 20)->default('EAN13'); // EAN13, UPC, CODE128, etc

            // Pool management
            $table->enum('status', ['available', 'assigned', 'reserved', 'legacy_archive', 'problematic'])
                ->default('available')
                ->index();

            // Legacy/Historical data tracking
            $table->boolean('is_legacy')->default(false)->index();
            $table->unsignedInteger('row_number')->nullable()->index(); // Original CSV row for reference
            $table->tinyInteger('quality_score')->nullable()->default(10); // 1-10 quality rating

            // Assignment tracking
            $table->foreignId('assigned_to_variant_id')->nullable()->constrained('product_variants')->onDelete('set null');
            $table->timestamp('assigned_at')->nullable()->index();
            $table->timestamp('date_first_used')->nullable(); // When first assigned (historical tracking)

            // Import and batch management
            $table->string('import_batch_id', 100)->nullable()->index(); // Track import batches

            // Legacy data preservation (from original CSV)
            $table->string('legacy_sku', 100)->nullable();
            $table->string('legacy_status', 50)->nullable();
            $table->string('legacy_product_name')->nullable();
            $table->string('legacy_brand', 100)->nullable();
            $table->string('legacy_updated', 50)->nullable();
            $table->text('legacy_notes')->nullable(); // Consolidated legacy information

            // General notes and metadata
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable(); // For future extensibility

            $table->timestamps();

            // Performance indexes for large dataset operations
            $table->index(['status', 'barcode_type']); // Primary assignment queries
            $table->index(['is_legacy', 'row_number']); // Legacy data queries
            $table->index(['assigned_to_variant_id', 'assigned_at']); // Assignment history
            $table->index(['import_batch_id', 'created_at']); // Batch processing
            $table->index(['barcode_type', 'quality_score', 'status']); // Smart assignment queries

            // Compound index for the most common assignment query
            $table->index(['status', 'barcode_type', 'is_legacy', 'row_number'], 'idx_assignment_priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('barcode_pool');
    }
};
