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
        Schema::create('sales_channels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type')->default('marketplace'); // marketplace, website, retail, etc.
            $table->decimal('default_fee_percentage', 5, 2)->default(0.00);
            $table->decimal('fixed_fee_amount', 10, 2)->nullable();
            $table->json('fee_structure')->nullable(); // For complex fee structures
            $table->string('currency', 3)->default('GBP');
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_channels');
    }
};
