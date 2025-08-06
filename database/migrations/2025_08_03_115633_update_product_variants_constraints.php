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
        Schema::table('product_variants', function (Blueprint $table) {
            // Drop the old unique constraint
            $table->dropUnique(['product_id', 'color', 'size']);
            
            // Make color and size nullable since we now have width and drop
            $table->string('color')->nullable()->change();
            $table->string('size')->nullable()->change();
            
            // Create a new unique constraint that includes all variant dimensions
            // This prevents duplicate variants with the same product_id, color, size, width, and drop
            $table->unique(['product_id', 'color', 'size', 'width', 'drop'], 'product_variants_unique_combination');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            // Drop the new unique constraint
            $table->dropUnique('product_variants_unique_combination');
            
            // Make color and size required again
            $table->string('color')->nullable(false)->change();
            $table->string('size')->nullable(false)->change();
            
            // Restore the old unique constraint
            $table->unique(['product_id', 'color', 'size']);
        });
    }
};
