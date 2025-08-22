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
            $table->string('filename'); // "product-123.jpg"
            $table->string('path')->nullable(); // R2 storage path
            $table->string('url'); // Full URL to image
            $table->string('mime_type', 50); // "image/jpeg"
            $table->integer('size'); // bytes (changed from file_size to match model)
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            
            // Image organization
            $table->boolean('is_primary')->default(false); // Primary image flag
            $table->integer('sort_order')->default(0); // Display order
            
            // DAM metadata
            $table->string('title')->nullable(); // User-friendly title
            $table->string('alt_text')->nullable(); // Accessibility text
            $table->text('description')->nullable();
            $table->string('folder', 100)->default('uncategorized'); // Organization
            $table->json('tags')->nullable(); // ["product", "variant", "lifestyle"]
            
            // Relationships (polymorphic - can attach to products, variants, etc)
            $table->string('imageable_type')->nullable(); // "App\Models\Product" (changed from attachable_type)
            $table->unsignedBigInteger('imageable_id')->nullable(); // (changed from attachable_id)
            
            // User tracking
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->onDelete('set null'); // (changed from user_id)
            
            $table->timestamps();
            
            // Indexes
            $table->index(['imageable_type', 'imageable_id']); // (updated from attachable_*)
            $table->index('folder');
            $table->index('created_by_user_id'); // (updated)
            $table->index('is_primary');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('images');
    }
};