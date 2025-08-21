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
            $table->string('url'); // Full URL to image
            $table->string('mime_type', 50); // "image/jpeg"
            $table->integer('file_size'); // bytes
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            
            // DAM metadata
            $table->string('title')->nullable(); // User-friendly title
            $table->string('alt_text')->nullable(); // Accessibility text
            $table->text('description')->nullable();
            $table->string('folder', 100)->default('uncategorized'); // Organization
            $table->json('tags')->nullable(); // ["product", "variant", "lifestyle"]
            
            // Relationships (polymorphic - can attach to products, variants, etc)
            $table->string('attachable_type')->nullable(); // "App\Models\Product"
            $table->unsignedBigInteger('attachable_id')->nullable();
            
            // User tracking
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            
            $table->timestamps();
            
            // Indexes
            $table->index(['attachable_type', 'attachable_id']);
            $table->index('folder');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('images');
    }
};