<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 🧹 SIMPLIFY SKU LINKS TABLE
     *
     * Remove confidence scoring and complex matching features.
     * Keep it simple: just link marketplace SKUs to internal SKUs.
     */
    public function up(): void
    {
        Schema::table('sku_links', function (Blueprint $table) {
            // Drop confidence index first (SQLite requires this order)
            $table->dropIndex(['confidence_score']);
        });

        Schema::table('sku_links', function (Blueprint $table) {
            // Then remove confidence and matching complexity columns
            $table->dropColumn([
                'confidence_score',
                'match_reason',
            ]);
        });

        // Drop the entire sku_mapping_rules table - not needed for simple linking
        Schema::dropIfExists('sku_mapping_rules');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sku_links', function (Blueprint $table) {
            // Restore confidence and matching fields
            $table->decimal('confidence_score', 5, 2)->default(0.00);
            $table->text('match_reason')->nullable();

            // Restore confidence index
            $table->index('confidence_score');
        });

        // Recreate sku_mapping_rules table if needed
        Schema::create('sku_mapping_rules', function (Blueprint $table) {
            $table->id();
            $table->string('marketplace');
            $table->string('pattern');
            $table->string('transformation')->nullable();
            $table->text('description')->nullable();
            $table->integer('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['marketplace', 'is_active']);
            $table->index('priority');
        });
    }
};
