<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('sku_links');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Not recreating the table as this is part of cleanup
        // If needed, refer to the original sku_links migration
    }
};
