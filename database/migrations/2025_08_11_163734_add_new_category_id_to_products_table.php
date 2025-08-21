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
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('new_category_id')->nullable()->after('category_id');
        });

        // Using cursor to avoid memory issues on large datasets
        DB::table('products')->whereNotNull('category_id')->orderBy('id')->cursor()->each(function ($product) {
            $category = DB::table('categories')->where('slug', $product->category_id)->first();

            if ($category) {
                DB::table('products')->where('id', $product->id)->update(['new_category_id' => $category->id]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('new_category_id');
        });
    }
};
