<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Essential Images Table - DAM System
     */
    public function up(): void
    {
        Schema::create('images', function (Blueprint $table) {
            $table->id();

            // Core image data
            $table->string('filename')->nullable(); // "product-123.jpg"
            $table->string('path')->nullable(); // R2 storage path
            $table->string('url'); // Full URL to image
            $table->string('mime_type', 50); // "image/jpeg"
            $table->integer('size'); // bytes (changed from file_size to match model)
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();

            // Image organization
            $table->boolean('is_primary')->default(false); // Primary image flag
            $table->integer('sort_order')->default(0); // Display order

            // Metadata
            $table->string('title')->nullable();
            $table->string('alt_text')->nullable();
            $table->text('description')->nullable();
            $table->string('folder', 100)->nullable();
            $table->json('tags')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('folder');
            $table->index('is_primary');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('images');
    }
};
