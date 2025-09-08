<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sync_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('sync_accounts', 'marketplace_type')) {
                $table->string('marketplace_type', 32)->nullable()->after('channel');
            }
            if (! Schema::hasColumn('sync_accounts', 'marketplace_subtype')) {
                $table->string('marketplace_subtype', 64)->nullable()->after('marketplace_type');
            }
            if (! Schema::hasColumn('sync_accounts', 'marketplace_template')) {
                $table->json('marketplace_template')->nullable()->after('marketplace_subtype');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sync_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('sync_accounts', 'marketplace_template')) {
                $table->dropColumn('marketplace_template');
            }
            if (Schema::hasColumn('sync_accounts', 'marketplace_subtype')) {
                $table->dropColumn('marketplace_subtype');
            }
            if (Schema::hasColumn('sync_accounts', 'marketplace_type')) {
                $table->dropColumn('marketplace_type');
            }
        });
    }
};

