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
        Schema::create('bulk_operations', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50); // 'pricing', 'images', 'attributes'
            $table->string('target_type', 50); // 'products', 'variants'
            $table->json('affected_ids'); // IDs of affected items
            $table->json('changes_applied'); // What was changed
            $table->json('original_values')->nullable(); // Original values for undo
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->boolean('can_undo')->default(true);
            $table->timestamp('undone_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'target_type']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bulk_operations');
    }
};
