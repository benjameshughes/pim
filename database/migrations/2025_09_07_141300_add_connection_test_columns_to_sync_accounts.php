<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sync_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('sync_accounts', 'connection_test_result')) {
                $table->json('connection_test_result')->nullable()->after('settings');
            }
            if (! Schema::hasColumn('sync_accounts', 'last_connection_test')) {
                $table->timestamp('last_connection_test')->nullable()->after('connection_test_result');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sync_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('sync_accounts', 'connection_test_result')) {
                $table->dropColumn('connection_test_result');
            }
            if (Schema::hasColumn('sync_accounts', 'last_connection_test')) {
                $table->dropColumn('last_connection_test');
            }
        });
    }
};

