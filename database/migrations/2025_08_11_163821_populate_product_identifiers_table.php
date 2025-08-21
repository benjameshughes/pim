<?php

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Migrate Linnworks SKUs from products table
        DB::table('products')->whereNotNull('linnworks_sku')->orderBy('id')->chunk(100, function ($products) {
            $aliases = [];
            foreach ($products as $product) {
                $aliases[] = [
                    'aliasable_id' => $product->id,
                    'aliasable_type' => Product::class,
                    'type' => 'linnworks_sku',
                    'value' => $product->linnworks_sku,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            DB::table('product_identifiers')->insert($aliases);
        });

        // Migrate External SKUs from product_variants table
        DB::table('product_variants')->whereNotNull('external_sku')->orderBy('id')->chunk(100, function ($variants) {
            $aliases = [];
            foreach ($variants as $variant) {
                $aliases[] = [
                    'aliasable_id' => $variant->id,
                    'aliasable_type' => ProductVariant::class,
                    'type' => 'external_sku',
                    'value' => $variant->external_sku,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            DB::table('product_identifiers')->insert($aliases);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('product_identifiers')->where('type', 'linnworks_sku')->delete();
        DB::table('product_identifiers')->where('type', 'external_sku')->delete();
    }
};
