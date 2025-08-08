<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For SQLite, we need to recreate the table to change the enum constraint
        // First, backup existing data
        $existingData = DB::table('barcode_pools')->get();

        // Drop the table (this will also drop indexes)
        Schema::dropIfExists('barcode_pools');

        // Recreate with new enum values
        Schema::create('barcode_pools', function (Blueprint $table) {
            $table->id();
            $table->string('barcode', 20)->unique();
            $table->string('barcode_type', 20)->default('EAN13');
            $table->enum('status', ['available', 'assigned', 'reserved', 'legacy_archive'])->default('available');
            $table->foreignId('assigned_to_variant_id')->nullable()->constrained('product_variants')->onDelete('set null');
            $table->timestamp('assigned_at')->nullable();
            $table->text('notes')->nullable();
            $table->text('legacy_notes')->nullable();
            $table->timestamp('date_first_used')->nullable();
            $table->boolean('is_legacy')->default(false);
            $table->string('import_batch_id', 50)->nullable();
            $table->timestamps();

            $table->index(['status', 'barcode_type']);
            $table->index('assigned_to_variant_id');
            $table->index(['status', 'is_legacy']);
            $table->index('import_batch_id');
        });

        // Restore existing data
        foreach ($existingData as $row) {
            DB::table('barcode_pools')->insert((array) $row);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Backup existing data
        $existingData = DB::table('barcode_pools')->get();

        // Drop the table
        Schema::dropIfExists('barcode_pools');

        // Recreate with original enum values
        Schema::create('barcode_pools', function (Blueprint $table) {
            $table->id();
            $table->string('barcode', 20)->unique();
            $table->string('barcode_type', 20)->default('EAN13');
            $table->enum('status', ['available', 'assigned', 'reserved'])->default('available');
            $table->foreignId('assigned_to_variant_id')->nullable()->constrained('product_variants')->onDelete('set null');
            $table->timestamp('assigned_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'barcode_type']);
            $table->index('assigned_to_variant_id');
        });

        // Restore existing data (filter out legacy_archive records)
        foreach ($existingData as $row) {
            $rowArray = (array) $row;
            if ($rowArray['status'] !== 'legacy_archive') {
                // Remove the new fields
                unset($rowArray['legacy_notes']);
                unset($rowArray['date_first_used']);
                unset($rowArray['is_legacy']);
                unset($rowArray['import_batch_id']);

                DB::table('barcode_pools')->insert($rowArray);
            }
        }
    }
};
