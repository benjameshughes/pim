<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * ✨ COMPREHENSIVE SYNC STATUS ENHANCEMENT ✨
     * Adding fabulous new fields for advanced sync monitoring!
     */
    public function up(): void
    {
        Schema::table('shopify_product_syncs', function (Blueprint $table) {
            // Advanced sync tracking fields
            $table->string('sync_method')->default('manual')->after('sync_status'); // manual, automatic, webhook
            $table->integer('variants_synced')->default(0)->after('sync_method'); // How many variants were synced
            $table->integer('sync_duration')->nullable()->after('variants_synced'); // Sync time in milliseconds
            $table->json('error_details')->nullable()->after('sync_duration'); // Store detailed error information
            $table->float('data_drift_score', 5, 2)->default(0.0)->after('error_details'); // Data drift scoring
            $table->integer('health_score')->default(100)->after('data_drift_score'); // Overall sync health 0-100
            
            // Add indexes for better performance on dashboard queries
            $table->index('sync_method');
            $table->index('data_drift_score');
            $table->index('health_score');
            $table->index(['sync_status', 'last_synced_at'], 'status_timestamp_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shopify_product_syncs', function (Blueprint $table) {
            // Remove indexes first
            $table->dropIndex('status_timestamp_index');
            $table->dropIndex(['health_score']);
            $table->dropIndex(['data_drift_score']);
            $table->dropIndex(['sync_method']);
            
            // Remove columns
            $table->dropColumn([
                'sync_method',
                'variants_synced',
                'sync_duration',
                'error_details',
                'data_drift_score',
                'health_score'
            ]);
        });
    }
};