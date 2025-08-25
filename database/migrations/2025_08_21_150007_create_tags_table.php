<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create tags table and product_tag pivot for tagging system
     */
    public function up(): void
    {
        // Create tags table
        Schema::create('tags', function (Blueprint $table) {
            $table->id();

            // Tag details
            $table->string('name')->unique(); // "clothing", "seasonal", "bestseller"
            $table->string('slug')->unique(); // "clothing", "seasonal", "bestseller"
            $table->string('color')->nullable(); // Hex color for UI display
            $table->text('description')->nullable();

            // Organization
            $table->string('type')->default('general'); // "general", "system", "auto"
            $table->integer('usage_count')->default(0); // How many products use this
            $table->boolean('is_active')->default(true);

            // User tracking
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->onDelete('set null');

            $table->timestamps();

            // Indexes
            $table->index('type');
            $table->index('usage_count');
            $table->index('is_active');
        });

        // Create product_tag pivot table
        Schema::create('product_tag', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('tag_id')->constrained()->onDelete('cascade');

            // Tag metadata
            $table->string('source')->default('manual'); // "manual", "auto", "import"
            $table->decimal('relevance_score', 3, 2)->default(1.00); // 0.00-1.00

            // User tracking
            $table->foreignId('tagged_by_user_id')->nullable()->constrained('users')->onDelete('set null');

            $table->timestamps();

            // Unique constraint
            $table->unique(['product_id', 'tag_id']);

            // Indexes
            $table->index('source');
            $table->index('relevance_score');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_tag');
        Schema::dropIfExists('tags');
    }
};
