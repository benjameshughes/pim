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
            // Remove the problematic unique constraint that prevents legitimate variants
            $table->dropUnique(['product_id', 'color', 'width']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            // Restore the unique constraint if rollback is needed
            $table->unique(['product_id', 'color', 'width']);
        });
    }
};
