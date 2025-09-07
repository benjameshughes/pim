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
            ->when($channel === 'shopify', fn ($q) => $q->where('sync_to_shopify', true))
            ->when($channel === 'ebay', fn ($q) => $q->where('sync_to_ebay', true))
            ->when($channel === 'mirakl', fn ($q) => $q->where('sync_to_mirakl', true))
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

        // 4) SalesChannel (for pricing attribute awareness)
        $channelCode = strtolower($this->account->channel).'_'.strtolower($this->account->name);
        $salesChannel = SalesChannel::where('code', $channelCode)->first();

        // 5) Basic sync status summary
        $statusSummary = [
            'synced' => DB::table('sync_statuses')->where('sync_account_id', $this->account->id)->where('sync_status', 'synced')->count(),
            'pending' => DB::table('sync_statuses')->where('sync_account_id', $this->account->id)->where('sync_status', 'pending')->count(),
            'failed' => DB::table('sync_statuses')->where('sync_account_id', $this->account->id)->where('sync_status', 'failed')->count(),
        ];

        return view('livewire.sync-accounts.dashboard', [
            'productsCount' => $productsCount,
            'attributeDefs' => $defs,
            'coverage' => $coverage,
            'salesChannel' => $salesChannel,
            'statusSummary' => $statusSummary,
        ]);
    }
}

