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
        Schema::create('marketplace_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')->constrained('product_variants')->onDelete('cascade');
            $table->foreignId('marketplace_id')->constrained()->onDelete('cascade');
            $table->string('marketplace_sku')->nullable(); // Their internal reference
            $table->string('title'); // Marketplace-specific title
            $table->text('description')->nullable(); // Marketplace-specific description
            $table->decimal('price_override', 10, 2)->nullable(); // NULL uses base price
            $table->enum('status', ['active', 'inactive', 'out_of_stock'])->default('active');
            $table->json('marketplace_data')->nullable(); // Platform-specific fields
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            
            $table->unique(['variant_id', 'marketplace_id']);
            $table->index(['marketplace_id', 'status']);
            $table->index('last_synced_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketplace_variants');
    }
};
