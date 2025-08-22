<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 🏊‍♂️ BARCODE POOL TABLE - GS1 BARCODE MANAGEMENT SYSTEM
     *
     * Comprehensive GS1 barcode pool with smart assignment from row 40,000+
     */
    public function up(): void
    {
        Schema::create('barcode_pool', function (Blueprint $table) {
            $table->id();
            
            // Core barcode data
            $table->string('barcode', 50)->unique(); // "1234567890123"
            $table->string('barcode_type', 20)->default('EAN13'); // EAN13, UPC, CODE128
            $table->string('status', 30)->default('available'); // available, assigned, reserved, legacy_archive, problematic
            
            // Legacy and quality tracking
            $table->boolean('is_legacy')->default(false); // Rows < 40,000 are legacy
            $table->unsignedInteger('row_number')->nullable(); // Original CSV row number
            $table->unsignedTinyInteger('quality_score')->default(10); // 1-10 quality assessment
            
            // Assignment tracking
            $table->foreignId('assigned_to_variant_id')->nullable()->constrained('product_variants')->onDelete('set null');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('date_first_used')->nullable(); // Historical tracking
            
            // Import and batch tracking
            $table->string('import_batch_id', 100)->nullable(); // Track import batches
            
            // Legacy data preservation (from original CSV)
            $table->string('legacy_sku', 100)->nullable();
            $table->string('legacy_status', 50)->nullable();
            $table->string('legacy_product_name', 255)->nullable();
            $table->string('legacy_brand', 100)->nullable();
            $table->string('legacy_updated', 50)->nullable();
            $table->text('legacy_notes')->nullable(); // Compiled legacy data
            
            // Additional metadata
            $table->text('notes')->nullable(); // Manual notes
            $table->json('metadata')->nullable(); // Flexible additional data
            
            $table->timestamps();
            
            // Performance indexes
            $table->index(['status', 'barcode_type']); // Primary assignment query
            $table->index(['is_legacy', 'row_number']); // Legacy vs active separation
            $table->index(['quality_score', 'row_number']); // Assignment priority
            $table->index('assigned_to_variant_id'); // Assignment lookups
            $table->index('import_batch_id'); // Batch tracking
            $table->index(['status', 'barcode_type', 'is_legacy', 'row_number']); // Composite for ready_for_assignment
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
