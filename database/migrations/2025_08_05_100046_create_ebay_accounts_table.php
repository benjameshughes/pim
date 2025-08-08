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
        Schema::create('ebay_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // User-friendly name for the account
            $table->string('ebay_user_id')->nullable(); // eBay user ID from OAuth
            $table->string('environment'); // SANDBOX or PRODUCTION
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->integer('expires_in')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->json('scopes')->nullable(); // Granted scopes
            $table->string('status')->default('pending'); // pending, active, expired, revoked
            $table->json('oauth_data')->nullable(); // Store additional OAuth response data
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['ebay_user_id', 'environment']); // One account per user per environment
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ebay_accounts');
    }
};
