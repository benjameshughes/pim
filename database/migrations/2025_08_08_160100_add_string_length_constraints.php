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
        // Add length constraints to improve performance and enforce data integrity
        
        // Products table
        Schema::table('products', function (Blueprint $table) {
            $table->string('name', 255)->change();
            $table->string('slug', 255)->change();
            $table->string('parent_sku', 50)->nullable()->change();
        });

        // Product variants table
        Schema::table('product_variants', function (Blueprint $table) {
            $table->string('color', 50)->nullable()->change();
            $table->string('size', 50)->nullable()->change();
            $table->string('sku', 100)->change(); // SKUs can be longer
            $table->string('width', 50)->nullable()->change();
            $table->string('drop', 50)->nullable()->change();
        });

        // Barcodes table
        Schema::table('barcodes', function (Blueprint $table) {
            $table->string('barcode', 50)->change(); // Most barcodes are 13-14 digits
            $table->string('barcode_type', 20)->change();
        });

        // Barcode pools table
        Schema::table('barcode_pools', function (Blueprint $table) {
            $table->string('barcode', 50)->change();
            $table->string('barcode_type', 20)->change();
        });

        // Product images table
        Schema::table('product_images', function (Blueprint $table) {
            $table->string('image_path', 500)->change(); // File paths can be long
            $table->string('original_filename', 255)->nullable()->change();
            $table->string('storage_disk', 50)->change();
            $table->string('mime_type', 100)->nullable()->change();
            $table->string('alt_text', 255)->nullable()->change();
        });

        // Pricing table
        Schema::table('pricing', function (Blueprint $table) {
            $table->string('marketplace', 100)->change();
            $table->string('currency', 3)->change(); // ISO currency codes are 3 chars
        });

        // Marketplaces table
        Schema::table('marketplaces', function (Blueprint $table) {
            $table->string('name', 100)->change();
            $table->string('platform', 50)->change();
            $table->string('code', 50)->change();
        });

        // Marketplace variants table
        Schema::table('marketplace_variants', function (Blueprint $table) {
            $table->string('marketplace_sku', 100)->nullable()->change();
            $table->string('title', 255)->change();
        });

        // Sales channels table (if exists)
        if (Schema::hasTable('sales_channels')) {
            Schema::table('sales_channels', function (Blueprint $table) {
                $table->string('name', 100)->change();
                $table->string('code', 50)->change();
            });
        }

        // Customers table
        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('first_name', 100)->nullable()->change();
                $table->string('last_name', 100)->nullable()->change();
                $table->string('email', 255)->nullable()->change();
                $table->string('phone', 20)->nullable()->change();
            });
        }

        // EbayAccounts table
        if (Schema::hasTable('ebay_accounts')) {
            Schema::table('ebay_accounts', function (Blueprint $table) {
                $table->string('name', 100)->change();
                $table->string('environment', 20)->change(); // sandbox/production
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to unlimited string lengths
        // Note: This is generally not recommended in production as it could truncate data
        
        Schema::table('products', function (Blueprint $table) {
            $table->string('name')->change();
            $table->string('slug')->change();
            $table->string('parent_sku')->nullable()->change();
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->string('color')->nullable()->change();
            $table->string('size')->nullable()->change();
            $table->string('sku')->change();
            $table->string('width')->nullable()->change();
            $table->string('drop')->nullable()->change();
        });

        Schema::table('barcodes', function (Blueprint $table) {
            $table->string('barcode')->change();
            $table->string('barcode_type')->change();
        });

        Schema::table('barcode_pools', function (Blueprint $table) {
            $table->string('barcode')->change();
            $table->string('barcode_type')->change();
        });

        Schema::table('product_images', function (Blueprint $table) {
            $table->string('image_path')->change();
            $table->string('original_filename')->nullable()->change();
            $table->string('storage_disk')->change();
            $table->string('mime_type')->nullable()->change();
            $table->string('alt_text')->nullable()->change();
        });

        Schema::table('pricing', function (Blueprint $table) {
            $table->string('marketplace')->change();
            $table->string('currency')->change();
        });

        Schema::table('marketplaces', function (Blueprint $table) {
            $table->string('name')->change();
            $table->string('platform')->change();
            $table->string('code')->change();
        });

        Schema::table('marketplace_variants', function (Blueprint $table) {
            $table->string('marketplace_sku')->nullable()->change();
            $table->string('title')->change();
        });

        if (Schema::hasTable('sales_channels')) {
            Schema::table('sales_channels', function (Blueprint $table) {
                $table->string('name')->change();
                $table->string('code')->change();
            });
        }

        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('first_name')->nullable()->change();
                $table->string('last_name')->nullable()->change();
                $table->string('email')->nullable()->change();
                $table->string('phone')->nullable()->change();
            });
        }

        if (Schema::hasTable('ebay_accounts')) {
            Schema::table('ebay_accounts', function (Blueprint $table) {
                $table->string('name')->change();
                $table->string('environment')->change();
            });
        }
    }
};