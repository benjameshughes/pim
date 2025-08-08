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
        Schema::create('barcode_pools', function (Blueprint $table) {
            $table->id();
            $table->string('barcode', 20)->unique();
            $table->string('barcode_type', 20)->default('EAN13');
            $table->enum('status', ['available', 'assigned', 'reserved'])->default('available');
            $table->foreignId('assigned_to_variant_id')->nullable()->constrained('product_variants')->onDelete('set null');
            $table->timestamp('assigned_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'barcode_type']);
            $table->index('assigned_to_variant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('barcode_pools');
    }
};
