<?php

namespace App\Livewire\SyncAccounts;

use App\Models\AttributeDefinition;
use App\Models\Product;
use App\Models\SalesChannel;
use App\Models\SyncAccount;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Dashboard extends Component
{
    public SyncAccount $account;

    public function mount(int $accountId)
    {
        $account = SyncAccount::findOrFail($accountId);
        $this->authorize('view', $account);
        $this->account = $account;
    }

    public function render()
    {
        $channel = $this->account->channel;

        // 1) Determine scope of products for this account (linked via SyncStatus)
        $productIds = DB::table('sync_statuses')
            ->where('sync_account_id', $this->account->id)
            ->pluck('product_id')
            ->unique()
            ->toArray();

        $productsCount = count($productIds);

        // 2) Channel-aware attribute definitions (hook into attribute system)
        $defs = AttributeDefinition::active()
            ->forMarketplace($channel)
            ->orderedForDisplay()
            ->get(['id', 'key', 'name', 'group', 'icon']);

        // 3) Coverage per attribute across products in scope
        $coverage = [];
        if ($productsCount > 0 && $defs->isNotEmpty()) {
            $defIds = $defs->pluck('id')->toArray();

            $raw = DB::table('product_attributes')
                ->select('attribute_definition_id', DB::raw('count(*) as total'), DB::raw('SUM(CASE WHEN is_valid = 1 THEN 1 ELSE 0 END) as valid'))
                ->whereIn('product_id', $productIds)
                ->whereIn('attribute_definition_id', $defIds)
                ->groupBy('attribute_definition_id')
                ->get()
                ->keyBy('attribute_definition_id');

            foreach ($defs as $def) {
                $row = $raw->get($def->id);
                $valid = $row->valid ?? 0;
                $total = $productsCount; // expectation: one value per product ideally
                $coverage[$def->key] = [
                    'definition' => $def,
                    'valid' => (int) $valid,
                    'missing' => max(0, $total - (int) $valid),
                    'coverage_pct' => $total > 0 ? round(($valid / $total) * 100, 1) : 0,
                ];
            }
        }

        // 4) Determine missing products for worst-covered attributes
        $topMissing = [];
        if ($productsCount > 0 && ! empty($coverage)) {
            $worst = collect($coverage)
                ->sortBy('coverage_pct')
                ->take(3)
                ->map(fn ($row) => $row['definition'])
                ->values();

            foreach ($worst as $def) {
                $missing = Product::query()
                    ->whereIn('id', $productIds)
                    ->whereDoesntHave('attributes', function ($q) use ($def) {
                        $q->where('attribute_definition_id', $def->id)
                          ->where('is_valid', true);
                    })
                    ->limit(5)
                    ->get(['id', 'name']);

                if ($missing->isNotEmpty()) {
                    $topMissing[] = [
                        'definition' => $def,
                        'products' => $missing,
                    ];
                }
            }
        }

        // 5) SalesChannel (for pricing attribute awareness)
        $channelCode = strtolower($this->account->channel).'_'.strtolower($this->account->name);
        $salesChannel = SalesChannel::where('code', $channelCode)->first();

        // 6) Basic sync status summary
        $statusSummary = [
            'synced' => DB::table('sync_statuses')->where('sync_account_id', $this->account->id)->where('status', 'synced')->count(),
            'pending' => DB::table('sync_statuses')->where('sync_account_id', $this->account->id)->where('status', 'pending')->count(),
            'failed' => DB::table('sync_statuses')->where('sync_account_id', $this->account->id)->where('status', 'failed')->count(),
        ];

        // 7) Shopify widget snapshot (rate limit) and link to webhooks
        $shopifyWidget = null;
        if ($channel === 'shopify') {
            $rate = $this->account->rateLimit() ?? [];
            $shopifyWidget = [
                'rate_limit' => [
                    'remaining' => $rate['remaining'] ?? null,
                    'limit' => $rate['limit'] ?? null,
                    'reset_at' => $rate['reset_at'] ?? ($rate['reset_ms'] ?? null),
                    'last_update' => $rate['updated_at'] ?? null,
                ],
                'webhooks_url' => route('shopify.webhooks'),
            ];
        }

        // 8) Mirakl offers snapshot from MarketplaceLinks
        $miraklWidget = null;
        if ($channel === 'mirakl') {
            $base = DB::table('marketplace_links')->where('sync_account_id', $this->account->id);
            $total = (clone $base)->count();
            $offersSynced = (clone $base)->where('marketplace_data->offers_synced', true)->count();
            $offersFailed = (clone $base)->where('marketplace_data->offers_sync_failed', true)->count();
            $miraklWidget = [
                'total_links' => $total,
                'offers_synced' => $offersSynced,
                'offers_failed' => $offersFailed,
            ];
        }

        return view('livewire.sync-accounts.dashboard', [
            'productsCount' => $productsCount,
            'attributeDefs' => $defs,
            'coverage' => $coverage,
            'salesChannel' => $salesChannel,
            'statusSummary' => $statusSummary,
            'topMissing' => $topMissing,
            'shopifyWidget' => $shopifyWidget,
            'miraklWidget' => $miraklWidget,
        ]);
    }
}
