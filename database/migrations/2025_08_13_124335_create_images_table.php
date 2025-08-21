<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 🔥✨ IMAGES TABLE - SIMPLE R2 STORAGE ✨🔥
     *
     * Clean, focused image storage for products
     * R2 URLs with background processing for thumbnails
     */
    public function up(): void
    {
        Schema::create('images', function (Blueprint $table) {
            $table->id();

            // Polymorphic relationship - can attach to products, variants, etc.
            $table->morphs('imageable');

            // Core image data
            $table->string('filename'); // Original filename
            $table->string('path'); // R2 storage path
            $table->string('url'); // Public R2 URL
            $table->string('thumbnail_url')->nullable(); // Generated thumbnail URL
            $table->string('optimized_url')->nullable(); // Optimized version URL

            // Image metadata
            $table->unsignedBigInteger('size'); // File size in bytes
            $table->unsignedInteger('width')->nullable(); // Image width
            $table->unsignedInteger('height')->nullable(); // Image height
            $table->string('mime_type'); // image/jpeg, image/png, etc.

            // Processing status
            $table->boolean('optimized')->default(false); // Has background processing completed?
            $table->boolean('is_primary')->default(false); // Primary image for the entity

            // Organization
            $table->unsignedInteger('sort_order')->default(0); // For image ordering

            $table->timestamps();

            // Additional indexes for performance (morphs() already creates imageable index)
            $table->index('is_primary');
            $table->index('optimized');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('images');
    }
};
