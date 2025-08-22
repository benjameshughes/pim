<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create sync_accounts table for marketplace integrations
     */
    public function up(): void
    {
        Schema::create('sync_accounts', function (Blueprint $table) {
            $table->id();
            
            // Account identification
            $table->string('name'); // "My Shopify Store"
            $table->string('channel'); // "shopify", "ebay", "amazon"
            $table->string('display_name')->nullable(); // User-friendly name
            
            // Status
            $table->boolean('is_active')->default(true);
            
            // Encrypted credentials and settings
            $table->text('credentials')->nullable(); // JSON encrypted credentials
            $table->json('settings')->nullable(); // Channel-specific settings
            
            $table->timestamps();
            
            // Indexes
            $table->index('channel');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_accounts');
    }
};