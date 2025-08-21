<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 🧹 SIMPLIFY IMAGES TABLE - REMOVE OPTIMIZATION FIELDS
     *
     * Remove complex optimization fields to keep image system simple
     */
    public function up(): void
    {
        Schema::table('images', function (Blueprint $table) {
            // Drop index first before dropping column
            $table->dropIndex('images_optimized_index');

            // Remove optimization-related fields
            $table->dropColumn([
                'thumbnail_url',
                'optimized_url',
                'optimized',
            ]);

            // Remove width/height since we're not extracting metadata anymore
            $table->dropColumn(['width', 'height']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('images', function (Blueprint $table) {
            // Add back the removed fields
            $table->string('thumbnail_url')->nullable();
            $table->string('optimized_url')->nullable();
            $table->boolean('optimized')->default(false);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();

            // Re-add index that was dropped
            $table->index('optimized');
        });
    }
};
