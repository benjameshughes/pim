<?php

use App\Models\Product;
use App\Models\Tag;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $products = Product::whereNotNull('tags')->where('tags', '!=', '')->get();

        foreach ($products as $product) {
            $tagNames = array_map('trim', explode(',', $product->tags));

            foreach ($tagNames as $tagName) {
                if (empty($tagName)) {
                    continue;
                }

                $tag = Tag::firstOrCreate(['name' => $tagName]);
                $product->tags()->attach($tag->id);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This will remove all tags and their relationships.
        // It doesn't restore the old `tags` column, that's handled in the next migration.
        DB::table('product_tag')->truncate();
        DB::table('tags')->truncate();
    }
};
