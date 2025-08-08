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
        Schema::create('shopify_taxonomy_categories', function (Blueprint $table) {
            $table->id();
            $table->string('shopify_id')->unique(); // e.g., gid://shopify/TaxonomyCategory/hg-d-wt-bs
            $table->string('name'); // e.g., "Blinds & Shades"
            $table->text('full_name'); // e.g., "Home & Garden > Decor > Window Treatments > Blinds & Shades"
            $table->integer('level'); // Depth in taxonomy tree
            $table->boolean('is_leaf'); // Whether this is a leaf category (no children)
            $table->boolean('is_root'); // Whether this is a root category
            $table->string('parent_id')->nullable(); // Parent category Shopify ID
            $table->json('children_ids')->nullable(); // Array of child category IDs
            $table->json('ancestor_ids')->nullable(); // Array of ancestor category IDs
            $table->json('attributes')->nullable(); // Category-specific attributes/metafields
            $table->timestamps();

            $table->index('shopify_id');
            $table->index('parent_id');
            $table->index('is_leaf');
            $table->index('level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shopify_taxonomy_categories');
    }
};
