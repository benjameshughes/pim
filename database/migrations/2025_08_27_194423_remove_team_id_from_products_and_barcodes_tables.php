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
        // Remove team_id from products table
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'team_id')) {
                $table->dropForeign(['team_id']);
                $table->dropColumn('team_id');
            }
        });

        // Remove team_id from barcodes table  
        Schema::table('barcodes', function (Blueprint $table) {
            if (Schema::hasColumn('barcodes', 'team_id')) {
                $table->dropForeign(['team_id']);
                $table->dropColumn('team_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add team_id back to products table
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'team_id')) {
                $table->foreignId('team_id')->after('id')->constrained()->cascadeOnDelete();
            }
        });

        // Add team_id back to barcodes table
        Schema::table('barcodes', function (Blueprint $table) {
            if (!Schema::hasColumn('barcodes', 'team_id')) {
                $table->foreignId('team_id')->after('id')->constrained()->cascadeOnDelete();
            }
        });
    }
};
