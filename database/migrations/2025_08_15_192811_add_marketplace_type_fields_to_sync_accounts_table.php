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
        Schema::table('sync_accounts', function (Blueprint $table) {
            // Add marketplace type fields for dynamic integration system
            $table->string('marketplace_type')->nullable()->after('channel')
                ->comment('Marketplace type: shopify, ebay, amazon, mirakl');

            $table->string('marketplace_subtype')->nullable()->after('marketplace_type')
                ->comment('Marketplace subtype/operator: bq, debenhams, freemans (for mirakl)');

            $table->json('marketplace_template')->nullable()->after('marketplace_subtype')
                ->comment('Cached marketplace template data');

            $table->timestamp('last_connection_test')->nullable()->after('marketplace_template')
                ->comment('Last successful connection test timestamp');

            $table->json('connection_test_result')->nullable()->after('last_connection_test')
                ->comment('Last connection test result details');

            // Add indexes for better query performance
            $table->index(['marketplace_type', 'marketplace_subtype'], 'idx_marketplace_types');
            $table->index(['marketplace_type', 'is_active'], 'idx_marketplace_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sync_accounts', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex('idx_marketplace_types');
            $table->dropIndex('idx_marketplace_active');

            // Drop columns
            $table->dropColumn([
                'marketplace_type',
                'marketplace_subtype',
                'marketplace_template',
                'last_connection_test',
                'connection_test_result',
            ]);
        });
    }
};
