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
            // Indexes
            $table->index('platform');
            $table->index('channel_code');
            $table->index('is_active');
        });

        // Backfill existing rows
        DB::table('sync_accounts')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $channel = strtolower($row->channel ?? '');
                $platform = $row->platform;
                $channelCode = $row->channel_code;

                // Decode JSON settings/credentials if needed
                $settings = json_decode($row->settings ?? '[]', true) ?: [];
                $credentials = json_decode(decrypt($row->credentials ?? ''), true) ?? null; // encrypted json
                if ($credentials === null) {
                    // credentials may not decrypt in this environment; fall back to null
                    $credentials = [];
                }

                // Determine platform
                if (empty($platform)) {
                    if (in_array($channel, ['freemans', 'debenhams', 'bq'])) {
                        $platform = 'mirakl';
                    } else {
                        $platform = $row->marketplace_type ?: $channel;
                    }
                }

                // Determine channel code
                if (empty($channelCode)) {
                    $channelCode = $row->marketplace_subtype ?: $channel;
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

