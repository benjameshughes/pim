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
        Schema::table('barcode_pools', function (Blueprint $table) {
            // Add fields for legacy tracking (status modification not needed as it already exists)
            $table->text('legacy_notes')->nullable()->after('notes');
            $table->timestamp('date_first_used')->nullable()->after('assigned_at');
            $table->boolean('is_legacy')->default(false)->after('notes');
            $table->string('import_batch_id', 50)->nullable()->after('is_legacy');
            
            // Add indexes for performance
            $table->index(['status', 'is_legacy']);
            $table->index('import_batch_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('barcode_pools', function (Blueprint $table) {
            $table->dropIndex(['status', 'is_legacy']);
            $table->dropIndex(['import_batch_id']);
            $table->dropColumn([
                'legacy_notes', 
                'date_first_used', 
                'is_legacy', 
                'import_batch_id'
            ]);
        });
    }
};
