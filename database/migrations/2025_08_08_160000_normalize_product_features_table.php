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
        // Create normalized product_features table
        Schema::create('product_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['feature', 'detail'])->default('feature');
            $table->string('title', 100)->nullable(); // Optional feature title
            $table->text('content'); // The actual feature/detail text
            $table->integer('sort_order')->default(0); // For maintaining order
            $table->timestamps();

            $table->index(['product_id', 'type', 'sort_order']);
            $table->index(['product_id', 'type']);
        });

        // Migrate existing data from numbered columns to normalized structure
        $productFeatureColumns = [
            'product_features_1', 'product_features_2', 'product_features_3', 
            'product_features_4', 'product_features_5'
        ];
        
        $productDetailColumns = [
            'product_details_1', 'product_details_2', 'product_details_3', 
            'product_details_4', 'product_details_5'
        ];

        // Get all products with feature/detail data
        $products = DB::table('products')
            ->whereNotNull('product_features_1')
            ->orWhereNotNull('product_features_2')
            ->orWhereNotNull('product_features_3')
            ->orWhereNotNull('product_features_4')
            ->orWhereNotNull('product_features_5')
            ->orWhereNotNull('product_details_1')
            ->orWhereNotNull('product_details_2')
            ->orWhereNotNull('product_details_3')
            ->orWhereNotNull('product_details_4')
            ->orWhereNotNull('product_details_5')
            ->get();

        foreach ($products as $product) {
            // Migrate features
            foreach ($productFeatureColumns as $index => $column) {
                if (!empty($product->$column)) {
                    DB::table('product_features')->insert([
                        'product_id' => $product->id,
                        'type' => 'feature',
                        'content' => $product->$column,
                        'sort_order' => $index + 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Migrate details
            foreach ($productDetailColumns as $index => $column) {
                if (!empty($product->$column)) {
                    DB::table('product_features')->insert([
                        'product_id' => $product->id,
                        'type' => 'detail',
                        'content' => $product->$column,
                        'sort_order' => $index + 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        // Drop the old anti-pattern columns
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'product_features_1', 'product_features_2', 'product_features_3', 
                'product_features_4', 'product_features_5',
                'product_details_1', 'product_details_2', 'product_details_3', 
                'product_details_4', 'product_details_5',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore the old column structure
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

        // Migrate data back from normalized structure (if needed)
        $features = DB::table('product_features')
            ->orderBy('product_id')
            ->orderBy('type')
            ->orderBy('sort_order')
            ->get()
            ->groupBy(['product_id', 'type']);

        foreach ($features as $productId => $types) {
            $updateData = [];

            if (isset($types['feature'])) {
                foreach ($types['feature']->take(5) as $index => $feature) {
                    $columnName = 'product_features_' . ($index + 1);
                    $updateData[$columnName] = $feature->content;
                }
            }

            if (isset($types['detail'])) {
                foreach ($types['detail']->take(5) as $index => $detail) {
                    $columnName = 'product_details_' . ($index + 1);
                    $updateData[$columnName] = $detail->content;
                }
            }

            if (!empty($updateData)) {
                DB::table('products')->where('id', $productId)->update($updateData);
            }
        }

        Schema::dropIfExists('product_features');
    }
};