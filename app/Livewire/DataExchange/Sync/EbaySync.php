<?php

namespace App\Livewire\DataExchange\Sync;

use App\Models\EbayAccount;
use App\Models\Product;
use App\Services\EbayConnectService;
use App\Services\EbayExportService;
use App\Services\EbayOAuthService;
use Exception;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class EbaySync extends Component
{
    use WithPagination;

    public $search = '';

    public $statusFilter = '';

    public $syncResults = [];

    public $isSyncing = false;

    public $connectionStatus = null;

    public $selectedProducts = [];

    public $selectAll = false;

    public $activeTab = 'export';

    public $selectedAccountId = null;

    public $showConnectModal = false;

    public $newAccountName = '';

    public $accounts = [];

    public $isConnecting = false;

    protected $queryString = ['search', 'statusFilter'];

    public function mount()
    {
        $this->loadAccounts();
        $this->testConnection();
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function loadAccounts()
    {
        $this->accounts = EbayAccount::active()
            ->environment(config('services.ebay.environment', 'SANDBOX'))
            ->latest()
            ->get();

        // Auto-select first account if none selected
        if (! $this->selectedAccountId && $this->accounts->isNotEmpty()) {
            $this->selectedAccountId = $this->accounts->first()->id;
        }
    }

    public function testConnection()
    {
        if (! $this->selectedAccountId) {
            $this->connectionStatus = [
                'success' => false,
                'message' => 'No eBay account selected. Please connect an account first.',
            ];

            return;
        }

        try {
            $account = EbayAccount::find($this->selectedAccountId);
            if (! $account) {
                $this->connectionStatus = [
                    'success' => false,
                    'message' => 'Selected eBay account not found.',
                ];

                return;
            }

            $oauthService = new EbayOAuthService;
            $tokenResult = $oauthService->getValidAccessToken($account);

            if ($tokenResult['success']) {
                $this->connectionStatus = [
                    'success' => true,
                    'message' => "Connected to eBay account: {$account->name}",
                    'account' => $account,
                ];
            } else {
                $this->connectionStatus = [
                    'success' => false,
                    'message' => $tokenResult['error'],
                ];
            }
        } catch (Exception $e) {
            $this->connectionStatus = [
                'success' => false,
                'message' => 'Connection test failed: '.$e->getMessage(),
            ];
        }
    }

    public function toggleSelectAll()
    {
        if ($this->selectAll) {
            $this->selectedProducts = Product::query()
                ->when($this->search, function ($query) {
                    $query->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('parent_sku', 'like', '%'.$this->search.'%');
                })
                ->pluck('id')
                ->toArray();
        } else {
            $this->selectedProducts = [];
        }
    }

    public function syncSelected()
    {
        if (empty($this->selectedProducts)) {
            session()->flash('error', 'Please select products to sync.');

            return;
        }

        $this->syncProducts($this->selectedProducts);
    }

    public function syncAll()
    {
        $productIds = Product::query()
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('parent_sku', 'like', '%'.$this->search.'%');
            })
            ->pluck('id')
            ->toArray();

        $this->syncProducts($productIds);
    }

    public function syncProduct($productId)
    {
        $this->syncProducts([$productId]);
    }

    private function syncProducts(array $productIds)
    {
        if (! $this->selectedAccountId) {
            session()->flash('error', 'Please select an eBay account first.');

            return;
        }

        $this->isSyncing = true;
        $this->syncResults = [];

        try {
            $account = EbayAccount::find($this->selectedAccountId);
            if (! $account) {
                throw new Exception('Selected eBay account not found.');
            }

            Log::info('Starting eBay sync', [
                'product_ids' => $productIds,
                'account_id' => $account->id,
                'account_name' => $account->name,
            ]);

            $products = Product::with(['variants.attributes', 'variants.pricing', 'variants.barcodes', 'attributes'])
                ->whereIn('id', $productIds)
                ->get();

            $ebayService = new EbayConnectService;
            $exportService = new EbayExportService($ebayService);

            $results = $exportService->exportProducts($products);

            $this->syncResults = [
                'total_products' => $results['total_products'],
                'total_variants' => $results['total_variants'],
                'successful_exports' => $results['successful_exports'],
                'failed_exports' => $results['failed_exports'],
                'errors' => $results['errors'],
                'exported_items' => $results['exported_items'],
            ];

            if ($results['successful_exports'] > 0) {
                session()->flash('message', "Successfully exported {$results['successful_exports']} variants to eBay!");
            }

            if ($results['failed_exports'] > 0) {
                session()->flash('error', "Failed to export {$results['failed_exports']} variants. Check the results below for details.");
            }

            Log::info('eBay sync completed', $this->syncResults);

        } catch (Exception $e) {
            $this->syncResults = [
                'error' => $e->getMessage(),
                'total_products' => count($productIds),
                'successful_exports' => 0,
                'failed_exports' => count($productIds),
            ];

            Log::error('eBay sync failed', [
                'error' => $e->getMessage(),
                'product_ids' => $productIds,
            ]);

            session()->flash('error', 'Sync failed: '.$e->getMessage());
        }

        $this->isSyncing = false;
        $this->selectedProducts = [];
        $this->selectAll = false;
    }

    public function clearResults()
    {
        $this->syncResults = [];
    }

    public function selectAccount($accountId)
    {
        $this->selectedAccountId = $accountId;
        $this->testConnection();
    }

    public function showConnectForm()
    {
        $this->showConnectModal = true;
        $this->newAccountName = '';
    }

    public function hideConnectForm()
    {
        $this->showConnectModal = false;
        $this->newAccountName = '';
        $this->isConnecting = false;
    }

    public function connectNewAccount()
    {
        $this->isConnecting = true;

        try {
            $oauthService = new EbayOAuthService;
            $result = $oauthService->generateAuthorizationUrl($this->newAccountName ?: null);

            Log::info('eBay OAuth authorization URL generated', [
                'success' => $result['success'],
                'url' => $result['authorization_url'] ?? null,
                'error' => $result['error'] ?? null,
            ]);

            if ($result['success']) {
                // Store account name in session for the callback
                if ($this->newAccountName) {
                    session(['ebay_oauth_account_name' => $this->newAccountName]);
                }

                // Use Livewire's redirect method
                $this->redirect($result['authorization_url'], navigate: false);
            } else {
                session()->flash('error', $result['error']);
                $this->hideConnectForm();
            }
        } catch (Exception $e) {
            Log::error('eBay OAuth connection failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            session()->flash('error', 'Failed to initiate eBay authorization: '.$e->getMessage());
            $this->hideConnectForm();
        }
    }

    public function removeAccount($accountId)
    {
        try {
            $account = EbayAccount::find($accountId);
            if ($account) {
                $oauthService = new EbayOAuthService;
                $result = $oauthService->revokeAccount($account);

                if ($result['success']) {
                    session()->flash('message', "Account '{$account->name}' has been removed.");

                    // Reload accounts and reset selection if needed
                    $this->loadAccounts();
                    if ($this->selectedAccountId == $accountId) {
                        $this->selectedAccountId = $this->accounts->first()?->id;
                        $this->testConnection();
                    }
                } else {
                    session()->flash('error', $result['error']);
                }
            }
        } catch (Exception $e) {
            session()->flash('error', 'Failed to remove account: '.$e->getMessage());
        }
    }

    public function render()
    {
        // Refresh accounts in case they were updated via OAuth callback
        $this->loadAccounts();

        $products = Product::query()
            ->with(['variants'])
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('parent_sku', 'like', '%'.$this->search.'%');
            })
            ->when($this->statusFilter, function ($query) {
                switch ($this->statusFilter) {
                    case 'synced':
                        $query->whereHas('variants.marketplaceVariants', function ($q) {
                            $q->whereHas('marketplace', function ($mq) {
                                $mq->where('platform', 'ebay');
                            });
                        });
                        break;
                    case 'not_synced':
                        $query->whereDoesntHave('variants.marketplaceVariants', function ($q) {
                            $q->whereHas('marketplace', function ($mq) {
                                $mq->where('platform', 'ebay');
                            });
                        });
                        break;
                }
            })
            ->latest()
            ->paginate(20);

        return view('livewire.data-exchange.sync.ebay-sync', [
            'products' => $products,
        ]);
    }
}
