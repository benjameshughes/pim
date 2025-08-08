<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For SQLite, we'll work around the enum limitation by treating it as a string constraint at the application level
        // Clean up any invalid status values first
        DB::statement("UPDATE barcode_pools SET status = 'available' WHERE status NOT IN ('available', 'assigned', 'reserved')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove any legacy_archive status records if they exist
        DB::statement("DELETE FROM barcode_pools WHERE status = 'legacy_archive'");
    }
};
