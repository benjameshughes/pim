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
            // Drop the unique constraint first
            $table->dropUnique(['product_id', 'color', 'size']);

            // Make color and size nullable
            $table->string('color')->nullable()->change();
            $table->string('size')->nullable()->change();

            // Re-add the unique constraint (this will work with nulls)
            $table->unique(['product_id', 'color', 'size']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            // Drop the unique constraint
            $table->dropUnique(['product_id', 'color', 'size']);

            // Make color and size required again
            $table->string('color')->nullable(false)->change();
            $table->string('size')->nullable(false)->change();

            // Re-add the unique constraint
            $table->unique(['product_id', 'color', 'size']);
        });
    }
};
