<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add foreign key constraint to pricing table after sales_channels table is created
     */
    public function up(): void
    {
        Schema::table('pricing', function (Blueprint $table) {
            $table->foreign('sales_channel_id')
                  ->references('id')
                  ->on('sales_channels')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pricing', function (Blueprint $table) {
            $table->dropForeign(['sales_channel_id']);
        });
    }
};
