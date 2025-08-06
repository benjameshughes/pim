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
        Schema::table('products', function (Blueprint $table) {
            $table->text('product_features_1')->nullable();
            $table->text('product_features_2')->nullable();
            $table->text('product_features_3')->nullable();
            $table->text('product_features_4')->nullable();
            $table->text('product_features_5')->nullable();
            $table->text('product_details_1')->nullable();
            $table->text('product_details_2')->nullable();
            $table->text('product_details_3')->nullable();
            $table->text('product_details_4')->nullable();
            $table->text('product_details_5')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'product_features_1', 'product_features_2', 'product_features_3', 'product_features_4', 'product_features_5',
                'product_details_1', 'product_details_2', 'product_details_3', 'product_details_4', 'product_details_5'
            ]);
        });
    }
};
