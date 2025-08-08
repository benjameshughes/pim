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
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->onDelete('cascade');
            $table->string('image_path'); // storage path
            $table->enum('image_type', ['main', 'detail', 'swatch', 'lifestyle', 'installation'])->default('detail');
            $table->integer('sort_order')->default(0);
            $table->string('alt_text')->nullable();
            $table->json('metadata')->nullable(); // size, format, etc.
            $table->timestamps();

            // Note: Check constraint would go here in production DB (SQLite doesn't support check method)

            $table->index(['product_id', 'image_type', 'sort_order']);
            $table->index(['variant_id', 'image_type', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
