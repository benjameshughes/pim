<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sync_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('sync_accounts', 'platform')) {
                $table->string('platform', 32)->nullable()->after('channel');
            }
            if (! Schema::hasColumn('sync_accounts', 'channel_code')) {
                $table->string('channel_code', 64)->nullable()->after('platform');
            }
            if (! Schema::hasColumn('sync_accounts', 'external_shop_id')) {
                $table->string('external_shop_id', 64)->nullable()->after('channel_code');
            }
            if (! Schema::hasColumn('sync_accounts', 'external_shop_name')) {
                $table->string('external_shop_name', 128)->nullable()->after('external_shop_id');
            }
            if (! Schema::hasColumn('sync_accounts', 'health_status')) {
                $table->string('health_status', 16)->nullable()->after('external_shop_name');
            }
        });

        // Create indexes only if they don't already exist
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            $indexes = DB::table(DB::raw('information_schema.STATISTICS'))
                ->select('INDEX_NAME')
                ->whereRaw('TABLE_SCHEMA = DATABASE()')
                ->where('TABLE_NAME', 'sync_accounts')
                ->pluck('INDEX_NAME')
                ->toArray();

            if (! in_array('sync_accounts_platform_index', $indexes)) {
                DB::statement('ALTER TABLE `sync_accounts` ADD INDEX `sync_accounts_platform_index`(`platform`)');
            }
            if (! in_array('sync_accounts_channel_code_index', $indexes)) {
                DB::statement('ALTER TABLE `sync_accounts` ADD INDEX `sync_accounts_channel_code_index`(`channel_code`)');
            }
            if (! in_array('sync_accounts_is_active_index', $indexes)) {
                DB::statement('ALTER TABLE `sync_accounts` ADD INDEX `sync_accounts_is_active_index`(`is_active`)');
            }
        } else {
            // For sqlite/pgsql/sqlsrv: attempt to add indexes and ignore failures
            try { Schema::table('sync_accounts', fn (Blueprint $t) => $t->index('platform')); } catch (\Throwable $e) {}
            try { Schema::table('sync_accounts', fn (Blueprint $t) => $t->index('channel_code')); } catch (\Throwable $e) {}
            try { Schema::table('sync_accounts', fn (Blueprint $t) => $t->index('is_active')); } catch (\Throwable $e) {}
        }

        // Backfill existing rows
        DB::table('sync_accounts')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $channel = strtolower($row->channel ?? '');
                $platform = $row->platform;
                $channelCode = $row->channel_code;

                // Decode JSON settings/credentials if needed
                $settings = json_decode($row->settings ?? '[]', true) ?: [];
                $credentials = [];
                try {
                    if (! empty($row->credentials)) {
                        $dec = decrypt($row->credentials);
                        $tmp = json_decode($dec, true);
                        if (is_array($tmp)) {
                            $credentials = $tmp;
                        }
                    }
                } catch (\Throwable $e) {
                    // Ignore decryption errors during migration backfill
                    $credentials = [];
                }

                // Determine platform
                if (empty($platform)) {
                    if (in_array($channel, ['freemans', 'debenhams', 'bq'])) {
                        $platform = 'mirakl';
                    } else {
                        $platform = isset($row->marketplace_type) && $row->marketplace_type
                            ? $row->marketplace_type
                            : $channel;
                    }
                }

                // Determine channel code
                if (empty($channelCode)) {
                    $channelCode = (isset($row->marketplace_subtype) && $row->marketplace_subtype)
                        ? $row->marketplace_subtype
                        : $channel;
                }

                $externalShopId = $settings['auto_fetched_data']['shop_id']
                    ?? ($credentials['shop_id'] ?? null);
                $externalShopName = $settings['auto_fetched_data']['shop_name'] ?? null;
                $health = $settings['health']['current']['status'] ?? null;

                DB::table('sync_accounts')->where('id', $row->id)->update([
                    'platform' => $platform,
                    'channel_code' => $channelCode,
                    'external_shop_id' => $externalShopId,
                    'external_shop_name' => $externalShopName,
                    'health_status' => $health,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('sync_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('sync_accounts', 'health_status')) {
                $table->dropColumn('health_status');
            }
            if (Schema::hasColumn('sync_accounts', 'external_shop_name')) {
                $table->dropColumn('external_shop_name');
            }
            if (Schema::hasColumn('sync_accounts', 'external_shop_id')) {
                $table->dropColumn('external_shop_id');
            }
            if (Schema::hasColumn('sync_accounts', 'channel_code')) {
                $table->dropColumn('channel_code');
            }
            if (Schema::hasColumn('sync_accounts', 'platform')) {
                $table->dropColumn('platform');
            }
        });
    }
};
