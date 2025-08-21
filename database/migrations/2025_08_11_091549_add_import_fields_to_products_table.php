<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 📤 ADD IMPORT FIELDS TO PRODUCTS TABLE
     *
     * Additional fields needed for CSV import functionality
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Import-specific fields
            $table->string('linnworks_sku')->nullable()->after('parent_sku'); // External system SKU
            $table->string('barcode')->nullable()->after('description'); // Product barcode

            // Physical dimensions (in cm)
            $table->integer('length')->nullable()->after('barcode');
            $table->integer('width')->nullable()->after('length');
            $table->integer('depth')->nullable()->after('width');
            $table->decimal('weight', 8, 2)->nullable()->after('depth'); // Weight in kg

            // Pricing
            $table->decimal('retail_price', 10, 2)->nullable()->after('weight');

            // Additional metadata fields
            $table->string('category_id')->nullable()->after('retail_price');
            $table->string('brand')->nullable()->after('category_id');
            $table->text('meta_description')->nullable()->after('brand');
            $table->json('tags')->nullable()->after('meta_description');

            // Indexes for better performance
            $table->index('linnworks_sku');
            $table->index('barcode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['linnworks_sku']);
            $table->dropIndex(['barcode']);

            $table->dropColumn([
                'linnworks_sku',
                'barcode',
                'length',
                'width',
                'depth',
                'weight',
                'retail_price',
                'category_id',
                'brand',
                'meta_description',
                'tags',
            ]);
        });
    }
};
