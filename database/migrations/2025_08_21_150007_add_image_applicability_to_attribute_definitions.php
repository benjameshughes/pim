<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attribute_definitions', function (Blueprint $table) {
            $table->boolean('is_applicable_for_images')->default(false)->after('is_required_for_variants');
            $table->index('is_applicable_for_images');
        });
    }

    public function down(): void
    {
        Schema::table('attribute_definitions', function (Blueprint $table) {
            $table->dropIndex(['is_applicable_for_images']);
            $table->dropColumn('is_applicable_for_images');
        });
    }
};

