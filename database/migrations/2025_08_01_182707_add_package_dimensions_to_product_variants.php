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
            $table->decimal('package_length', 8, 2)->nullable();
            $table->decimal('package_width', 8, 2)->nullable();
            $table->decimal('package_height', 8, 2)->nullable();
            $table->decimal('package_weight', 8, 3)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn(['package_length', 'package_width', 'package_height', 'package_weight']);
        });
    }
};
