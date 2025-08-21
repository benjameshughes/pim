<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Essential Categories Table - Simple Hierarchy
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            
            // Category data
            $table->string('name'); // "Window Treatments"
            $table->string('slug')->unique(); // "window-treatments"
            $table->text('description')->nullable();
            
            // Simple hierarchy (parent/child)
            $table->foreignId('parent_id')->nullable()->constrained('categories')->onDelete('cascade');
            
            // Organization
            $table->integer('sort_order')->default(0);
            $table->string('status', 20)->default('active');
            
            $table->timestamps();
            
            // Indexes
            $table->index('parent_id');
            $table->index('status');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};