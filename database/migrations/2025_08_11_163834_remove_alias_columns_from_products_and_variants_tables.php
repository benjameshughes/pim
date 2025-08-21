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
        // Fix SQLite column dropping issues by handling indexes first

        // Handle products table - drop linnworks_sku column and its index
        if (Schema::hasColumn('products', 'linnworks_sku')) {
            Schema::table('products', function (Blueprint $table) {
                // Drop the index first (SQLite requirement)
                try {
                    $table->dropIndex(['linnworks_sku']);
                } catch (\Exception $e) {
                    // Index might not exist, continue
                }
                // Now drop the column
                $table->dropColumn('linnworks_sku');
            });
        }

        // Handle product_variants table - drop external_sku column
        if (Schema::hasColumn('product_variants', 'external_sku')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->dropColumn('external_sku');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('linnworks_sku')->nullable();
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->string('external_sku')->nullable();
        });
    }
};
