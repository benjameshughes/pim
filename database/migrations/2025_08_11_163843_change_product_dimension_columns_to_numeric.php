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
            $table->decimal('length', 8, 2)->nullable()->change();
            $table->decimal('width', 8, 2)->nullable()->change();
            $table->decimal('depth', 8, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->integer('length')->nullable()->change();
            $table->integer('width')->nullable()->change();
            $table->integer('depth')->nullable()->change();
        });
    }
};
