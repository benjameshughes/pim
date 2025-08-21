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
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->string('parent_sku');
            $table->foreignIdFor(\App\Models\Product::class)->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('marketplace');
            $table->string('account');
            $table->timestamps();

            // Add indexes for better performance
            $table->index(['product_id', 'marketplace', 'account']);
            $table->index('parent_sku');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
