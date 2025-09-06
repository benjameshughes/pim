<?php

namespace App\Livewire\Marketplace;

use App\Actions\Marketplace\CreateMarketplaceIntegrationAction;
use App\Services\Marketplace\MarketplaceRegistry;
use App\Services\Mirakl\MiraklOperatorDetectionService;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('components.layouts.app')]
class AddIntegrationWizard extends Component
{
    /**
     * 🎯 WIZARD STATE MANAGEMENT
     */
    public int $currentStep = 1;

    public const TOTAL_STEPS = 2;

    /**
     * 📋 FORM DATA
     */
    #[Validate('required|string')]
    public string $selectedMarketplace = '';

    #[Validate('nullable|string')]
    public string $selectedOperator = '';

    #[Validate('required|string|max:255')]
    public string $displayName = '';

    /** @var array<string, mixed> */
    public array $credentials = [];

    /** @var array<string, mixed> */
    public array $settings = [];

    /**
     * 🔍 COMPONENT STATE
     */
    /** @var array<string, mixed> */
    public array $availableMarketplaces = [];

    /** @var array<string, mixed> */
    public array $availableOperators = [];

    /** @var array<string, string> */
    public array $validationErrors = [];

    public bool $isLoading = false;

    public bool $connectionTestPassed = false;

    /** @var array<string, mixed>|null */
    public ?array $connectionTestResult = null;

    protected MarketplaceRegistry $marketplaceRegistry;

    // Use Livewire v3 event attributes for child → parent events
    #[On('marketplaceSelected')]
    public function onMarketplaceSelected(string $type): void
    {
        $this->selectMarketplace($type);
    }

    /**
     * 🚀 COMPONENT INITIALIZATION
     */
    public function mount(): void
    {
        $this->loadAvailableMarketplaces();
    }

    /**
     * 📋 LOAD AVAILABLE MARKETPLACES
     */
    private function loadAvailableMarketplaces(): void
    {
        $this->availableMarketplaces = $this->getMarketplaceRegistry()
            ->getAvailableMarketplaces()
            ->map(fn ($template) => [
                'type' => $template->type,
                'name' => $template->name,
                'description' => $template->description,
                'has_operators' => $template->hasOperators(),
                'logo_url' => $template->logoUrl,
            ])
            ->toArray();
    }

    // --- Livewire v3 child event handlers ---

    #[On('configurationUpdated')]
    public function onConfigurationUpdated(string $displayName, array $credentials): void
    {
        $this->displayName = $displayName;
        $this->credentials = $credentials;
        $this->nextStep();
    }

    #[On('configurationPatched')]
    public function onConfigurationPatched(string $displayName, array $credentials, array $settings): void
    {
        $this->displayName = $displayName;
        $this->credentials = $credentials;
        $this->settings = $settings;
    }

    #[On('previousStep')]
    public function onPreviousStep(): void
    {
        $this->previousStep();
    }

    #[On('testConnectionRequested')]
    public function onTestConnectionRequested(): void
    {
        $this->testConnection();
    }

    #[On('createIntegrationRequested')]
    public function onCreateIntegrationRequested(): void
    {
        $this->completeWizard();
    }

    /**
     * 🎯 STEP 1: MARKETPLACE SELECTION
     */
    public function selectMarketplace(string $marketplace): void
    {
        $this->selectedMarketplace = $marketplace;
        $template = $this->getMarketplaceRegistry()->getMarketplaceTemplate($marketplace);

        // For Mirakl, we skip operator selection and use auto-detection
        if ($marketplace === 'mirakl') {
            $this->availableOperators = [];
            $this->selectedOperator = ''; // Will be auto-detected later
            $this->displayName = 'Mirakl Integration';
        } else {
            // Legacy behavior for other marketplaces that still use operators
            if ($template && $template->hasOperators()) {
                $this->availableOperators = collect($template->supportedOperators)
                    ->map(function ($config, $key) {
                        return [
                            'type' => $key,
                            'name' => $config['display_name'],
                            'description' => $config['description'],
                            'currency' => $config['currency'] ?? 'GBP',
                            'status' => $config['status'] ?? 'active',
                        ];
                    })
                    ->toArray();
            } else {
                $this->availableOperators = [];
                $this->selectedOperator = '';
            }

            // Generate default display name
            $this->displayName = ($template ? $template->name : 'Unknown').' Integration';
        }

        $this->nextStep();
    }

    /**
     * 🏢 SELECT OPERATOR (FOR MIRAKL)
     */
    public function selectOperator(string $operator): void
    {
        $this->selectedOperator = $operator;

        // Update display name to include operator
        if ($this->selectedMarketplace && $this->selectedOperator) {
            $template = $this->getMarketplaceRegistry()->getMarketplaceTemplate($this->selectedMarketplace);
            $operatorData = $template->supportedOperators[$operator] ?? [];
            $this->displayName = $operatorData['display_name'] ?? (($template ? $template->name : 'Unknown').' Integration');
        }

        $this->nextStep();
    }

    /**
     * ⚙️ STEP 2/3: CONFIGURATION
     */
    public function updateConfiguration(): void
    {
        // Build attribute-based rules using the registry so Livewire
        // can attach errors to the correct credential inputs.
        $rules = [
            'displayName' => 'required|string|max:255',
        ];

        $fieldRules = $this->validationRules;
        foreach ($fieldRules as $field => $ruleSet) {
            $rules["credentials.{$field}"] = $ruleSet;
        }

        // Validate; if it fails, Livewire surfaces inline errors next to inputs
        $this->validate($rules);

        // All good, advance to next step
        $this->nextStep();
    }

    /**
     * 🔍 MIRAKL: Fetch Store Information with Auto-Detection
     *
     * For Mirakl integrations, automatically:
     * 1. Detect operator type from URL/API response
     * 2. Fetch shop name and shop ID from /api/account endpoint
     * 3. Set integration display name based on detected operator
     */
    public function fetchMiraklStoreInfo(): void
    {
        if ($this->selectedMarketplace !== 'mirakl') {
            return;
        }

        $baseUrl = $this->credentials['base_url'] ?? '';
        $apiKey = $this->credentials['api_key'] ?? '';

        if (empty($baseUrl) || empty($apiKey)) {
            session()->flash('error', 'Please provide both Base URL and API Key before fetching store information.');

            return;
        }

        $this->isLoading = true;

        try {
            // Step 1: Auto-detect operator type
            $detectionService = new MiraklOperatorDetectionService;
            $detectionResult = $detectionService->detectOperator($baseUrl, $apiKey);

            if (! $detectionResult['success']) {
                throw new \Exception("Unable to detect operator: {$detectionResult['message']}");
            }

            // Step 2: Store detected operator information
            $operator = $detectionResult['operator'];
            $accountData = $detectionResult['account_data'];

            $this->selectedOperator = $operator;

            // Step 3: Auto-populate shop information from account data
            if (! $accountData || ! isset($accountData['shop_name'], $accountData['shop_id'])) {
                throw new \Exception('Invalid response from Mirakl API. Shop information not found.');
            }

            $this->credentials['shop_name'] = $accountData['shop_name'];
            $this->credentials['shop_id'] = (string) $accountData['shop_id'];

            // Step 4: Update display name with detected operator info
            $operatorInfo = $detectionService->getOperatorDisplayInfo($operator);
            $this->displayName = $accountData['shop_name'].' ('.$operatorInfo['display_name'].')';

            // Step 5: Store comprehensive auto-fetched data in settings
            $this->settings['auto_fetched_data'] = [
                'shop_name' => $accountData['shop_name'],
                'shop_id' => $accountData['shop_id'],
                'currency' => $accountData['currency_iso_code'] ?? null,
                'shop_state' => $accountData['shop_state'] ?? null,
                'fetched_at' => now()->toISOString(),
            ];

            $this->settings['operator_detection'] = [
                'detected_operator' => $operator,
                'detection_method' => $detectionResult['detection_method'],
                'confidence' => $detectionResult['confidence'],
                'evidence' => $detectionResult['evidence'],
                'duration_ms' => $detectionResult['duration_ms'],
                'metadata' => $detectionResult['metadata'],
            ];

            session()->flash('success',
                '✅ Store information fetched successfully! '.
                "Detected: {$operatorInfo['display_name']} | ".
                "Shop: {$accountData['shop_name']} (ID: {$accountData['shop_id']}) | ".
                "Method: {$detectionResult['detection_method']} ({$detectionResult['confidence']} confidence)"
            );

        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();

            // Provide helpful error messages
            if (str_contains($errorMessage, '401') || str_contains($errorMessage, 'Unauthorized')) {
                $errorMessage = 'Invalid API key. Please check your credentials.';
            } elseif (str_contains($errorMessage, '403') || str_contains($errorMessage, 'Forbidden')) {
                $errorMessage = 'Access denied. Please check your API key permissions.';
            } elseif (str_contains($errorMessage, '404') || str_contains($errorMessage, 'Not Found')) {
                $errorMessage = 'API endpoint not found. Please verify your base URL points to a valid Mirakl instance.';
            } elseif (str_contains($errorMessage, 'timeout') || str_contains($errorMessage, 'timed out')) {
                $errorMessage = 'Connection timed out. Please check your base URL and network connection.';
            } elseif (str_contains($errorMessage, 'Unable to fetch account data')) {
                $errorMessage = 'Could not connect to Mirakl API. Please check your base URL and API key.';
            }

            Log::error('❌ Mirakl store info fetch failed', [
                'base_url' => $baseUrl,
                'api_key_prefix' => substr($apiKey, 0, 8).'...',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            session()->flash('error', "❌ Failed to fetch store information: {$errorMessage}");
        }

        $this->isLoading = false;
    }

    /**
     * 🧪 STEP 4: CONNECTION TEST
     */
    public function testConnection(): void
    {
        $this->isLoading = true;
        $this->connectionTestResult = null;

        try {
            // For Mirakl integrations, ensure store info is fetched first
            if ($this->selectedMarketplace === 'mirakl' && empty($this->credentials['shop_id'])) {
                $this->fetchMiraklStoreInfo();
                if (empty($this->credentials['shop_id'])) {
                    throw new \Exception('Unable to fetch Mirakl store information. Please check your credentials.');
                }
            }

            // Create temporary account data for testing
            $testData = [
                'marketplace_type' => $this->selectedMarketplace,
                'marketplace_subtype' => $this->selectedOperator ?: null,
                'display_name' => $this->displayName,
                'credentials' => $this->credentials,
                'settings' => $this->settings,
            ];

            // Create temporary MarketplaceCredentials for testing only (don't save to database)
            $credentials = $this->createMarketplaceCredentials(
                $this->selectedMarketplace,
                $this->credentials,
                $this->settings,
                $this->selectedOperator ?: null
            );

            // Test connection directly with credentials (no database save)
            $connectionResult = $this->testConnectionWithCredentials($credentials, $this->selectedMarketplace);

            $this->connectionTestResult = $connectionResult->toArray();
            $this->connectionTestPassed = $connectionResult->success;

            if ($this->connectionTestPassed) {
                // Enrich credentials/settings with store info when available
                if ($this->selectedMarketplace === 'shopify') {
                    $details = $this->connectionTestResult['details'] ?? [];
                    if (!empty($details)) {
                        $this->credentials['store_url'] = $details['store_url'] ?? ($this->credentials['store_url'] ?? '');
                        $this->credentials['shop_name'] = $details['shop_name'] ?? ($this->credentials['shop_name'] ?? '');
                        $this->credentials['shop_id'] = (string) ($details['shop_id'] ?? ($this->credentials['shop_id'] ?? ''));
                        $this->credentials['domain'] = $details['domain'] ?? ($this->credentials['domain'] ?? '');

                        $this->settings['auto_fetched_data'] = [
                            'shop_name' => $this->credentials['shop_name'],
                            'shop_id' => $this->credentials['shop_id'],
                            'domain' => $this->credentials['domain'],
                            'currency' => $details['currency'] ?? '',
                            'timezone' => $details['timezone'] ?? '',
                            'plan_name' => $details['plan_name'] ?? '',
                            'api_version' => $details['api_version'] ?? ($this->credentials['api_version'] ?? ''),
                            'fetched_at' => now()->toISOString(),
                        ];

                        // If display name was generic, upgrade it to the shop name
                        if (empty($this->displayName) || str_contains(strtolower($this->displayName), 'integration')) {
                            $this->displayName = $this->credentials['shop_name'].' (Shopify)';
                        }
                    }
                }

                $shopInfo = '';
                if ($this->selectedMarketplace === 'mirakl' && ! empty($this->credentials['shop_name'])) {
                    $shopInfo = " for {$this->credentials['shop_name']} (ID: {$this->credentials['shop_id']})";
                }
                if ($this->selectedMarketplace === 'shopify' && ! empty($this->credentials['shop_name'])) {
                    $shopInfo = " for {$this->credentials['shop_name']} (ID: {$this->credentials['shop_id']})";
                }
                session()->flash('success', "Connection test successful{$shopInfo}! Integration is ready to be created.");
            } else {
                session()->flash('error', 'Connection test failed. Please check your credentials.');
            }

        } catch (\Exception $e) {
            $this->connectionTestResult = [
                'success' => false,
                'message' => $e->getMessage(),
                'recommendations' => ['Please check your input data and try again.'],
            ];
            $this->connectionTestPassed = false;
            session()->flash('error', 'Connection test failed: '.$e->getMessage());
        }

        $this->isLoading = false;
    }

    /**
     * ✅ COMPLETE WIZARD: CREATE INTEGRATION
     */
    public function completeWizard(): mixed
    {
        $this->isLoading = true;

        try {
            $integrationData = [
                'marketplace_type' => $this->selectedMarketplace,
                'marketplace_subtype' => $this->selectedOperator ?: null,
                'display_name' => $this->displayName,
                'credentials' => $this->credentials,
                'settings' => $this->settings,
            ];

            $createAction = new CreateMarketplaceIntegrationAction($this->getMarketplaceRegistry());
            $account = $createAction->execute($integrationData);

            // Setup identifiers via fluent Sync chain for readability
            \App\Services\Marketplace\Facades\Sync::marketplace($account->channel)
                ->account($account->name)
                ->setupIdentifiers();

            session()->flash('success', "Successfully created {$this->displayName} integration!");

            // Redirect to marketplace identifiers dashboard
            return $this->redirect('/marketplace/identifiers');

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to create integration: '.$e->getMessage());
        }

        $this->isLoading = false;

        return null;
    }

    /**
     * 🔄 NAVIGATION METHODS
     */
    public function nextStep(): void
    {
        if ($this->currentStep < self::TOTAL_STEPS) {
            $this->currentStep++;
        }
    }

    public function previousStep(): void
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;
        }
    }

    public function goToStep(int $step): void
    {
        if ($step >= 1 && $step <= self::TOTAL_STEPS) {
            $this->currentStep = $step;
        }
    }

    /**
     * 🔄 RESET WIZARD
     */
    public function resetWizard(): void
    {
        $this->currentStep = 1;
        $this->selectedMarketplace = '';
        $this->selectedOperator = '';
        $this->displayName = '';
        $this->credentials = [];
        $this->settings = [];
        $this->availableOperators = [];
        $this->connectionTestPassed = false;
        $this->connectionTestResult = null;
        $this->validationErrors = [];
    }

    /**
     * 📊 COMPUTED PROPERTIES
     */
    public function getStepTitleProperty(): string
    {
        return match ($this->currentStep) {
            1 => 'Select Marketplace',
            2 => 'Configure & Fetch Store Info',
            default => 'Unknown Step',
        };
    }

    public function getCanFetchMiraklInfoProperty(): bool
    {
        return ! empty($this->credentials['base_url']) &&
               ! empty($this->credentials['api_key']) &&
               ! $this->isLoading;
    }

    public function getProgressPercentageProperty(): int
    {
        return (int) round(($this->currentStep / self::TOTAL_STEPS) * 100);
    }

    /** @return array<int, string> */
    public function getRequiredFieldsProperty(): array
    {
        if (! $this->selectedMarketplace) {
            return [];
        }

        return $this->getMarketplaceRegistry()->getRequiredFields(
            $this->selectedMarketplace,
            $this->selectedOperator ?: null
        );
    }

    /** @return array<string, mixed> */
    public function getValidationRulesProperty(): array
    {
        if (! $this->selectedMarketplace) {
            return [];
        }

        return $this->getMarketplaceRegistry()->getValidationRules(
            $this->selectedMarketplace,
            $this->selectedOperator ?: null
        );
    }

    /**
     * 🧪 TEST CONNECTION WITH CREDENTIALS (WITHOUT SAVING)
     */
    protected function testConnectionWithCredentials(\App\ValueObjects\MarketplaceCredentials $credentials, string $marketplaceType): \App\ValueObjects\ConnectionTestResult
    {
        try {
            return match ($marketplaceType) {
                'mirakl' => $this->testMiraklConnectionDirect($credentials),
                'shopify' => $this->testShopifyConnectionDirect($credentials),
                default => \App\ValueObjects\ConnectionTestResult::failure("Connection test not implemented for {$marketplaceType}")
            };
        } catch (\Exception $e) {
            return \App\ValueObjects\ConnectionTestResult::failure("Connection test failed: {$e->getMessage()}");
        }
    }

    /**
     * 🏢 TEST MIRAKL CONNECTION DIRECTLY
     */
    protected function testMiraklConnectionDirect(\App\ValueObjects\MarketplaceCredentials $credentials): \App\ValueObjects\ConnectionTestResult
    {
        $baseUrl = $credentials->getCredential('base_url');
        $apiKey = $credentials->getCredential('api_key');
        $operatorType = $credentials->getOperator();

        if (empty($baseUrl)) {
            return \App\ValueObjects\ConnectionTestResult::failure('Missing base_url credential for Mirakl connection test');
        }

        if (empty($apiKey)) {
            return \App\ValueObjects\ConnectionTestResult::failure('Missing api_key credential for Mirakl connection test');
        }

        $endpoint = rtrim($baseUrl, '/').'/api/account';

        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(10)->get($endpoint);

            if ($response->successful()) {
                $accountData = $response->json();

                return \App\ValueObjects\ConnectionTestResult::success(
                    'Successfully connected to Mirakl API',
                    [
                        'operator_type' => $operatorType,
                        'base_url' => $baseUrl,
                        'shop_id' => $accountData['shop_id'] ?? 'Unknown',
                        'shop_name' => $accountData['shop_name'] ?? 'Unknown',
                        'currency' => $accountData['currency_iso_code'] ?? 'Unknown',
                        'shop_state' => $accountData['shop_state'] ?? 'Unknown',
                    ],
                    null,
                    $endpoint
                );
            }

            return \App\ValueObjects\ConnectionTestResult::failure(
                'Mirakl API returned error: '.$response->status(),
                ['status_code' => $response->status(), 'response' => $response->body()]
            );

        } catch (\Exception $e) {
            return \App\ValueObjects\ConnectionTestResult::failure(
                'Failed to connect to Mirakl: '.$e->getMessage(),
                ['endpoint' => $endpoint, 'operator' => $operatorType]
            );
        }
    }

    /**
     * 🛍️ TEST SHOPIFY CONNECTION DIRECTLY
     */
    protected function testShopifyConnectionDirect(\App\ValueObjects\MarketplaceCredentials $credentials): \App\ValueObjects\ConnectionTestResult
    {
        $storeUrl = $credentials->getCredential('store_url');
        $accessToken = $credentials->getCredential('access_token');
        $apiVersion = $credentials->getCredential('api_version') ?: '2025-07';

        if (empty($storeUrl)) {
            return \App\ValueObjects\ConnectionTestResult::failure('Missing store_url credential for Shopify connection test');
        }

        if (empty($accessToken)) {
            return \App\ValueObjects\ConnectionTestResult::failure('Missing access_token credential for Shopify connection test');
        }

        // Ensure store URL ends with .myshopify.com
        if (! str_contains($storeUrl, '.myshopify.com')) {
            $storeUrl = rtrim($storeUrl, '/').'.myshopify.com';
        }

        // Build API endpoint
        $endpoint = "https://{$storeUrl}/admin/api/{$apiVersion}/shop.json";

        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'X-Shopify-Access-Token' => $accessToken,
                'Content-Type' => 'application/json',
            ])->timeout(10)->get($endpoint);

            if ($response->successful()) {
                $shopData = $response->json('shop');

                return \App\ValueObjects\ConnectionTestResult::success(
                    'Successfully connected to Shopify store',
                    [
                        'store_url' => $storeUrl,
                        'shop_id' => $shopData['id'] ?? 'Unknown',
                        'shop_name' => $shopData['name'] ?? 'Unknown',
                        'domain' => $shopData['domain'] ?? 'Unknown',
                        'currency' => $shopData['currency'] ?? 'Unknown',
                        'timezone' => $shopData['timezone'] ?? 'Unknown',
                        'plan_name' => $shopData['plan_name'] ?? 'Unknown',
                        'api_version' => $apiVersion,
                    ],
                    null,
                    $endpoint
                );
            }

            return \App\ValueObjects\ConnectionTestResult::failure(
                'Shopify API returned error: '.$response->status(),
                ['status_code' => $response->status(), 'response' => $response->body()]
            );

        } catch (\Exception $e) {
            return \App\ValueObjects\ConnectionTestResult::failure(
                'Failed to connect to Shopify: '.$e->getMessage(),
                ['endpoint' => $endpoint, 'store_url' => $storeUrl]
            );
        }
    }

    /**
     * 🛍️ FETCH SHOPIFY STORE INFORMATION
     *
     * For Shopify integrations, automatically:
     * 1. Validate store URL format
     * 2. Fetch shop information from /admin/api/{version}/shop.json endpoint
     * 3. Set integration display name based on shop name
     */
    public function fetchShopifyStoreInfo(): void
    {
        if ($this->selectedMarketplace !== 'shopify') {
            return;
        }

        $storeUrl = $this->credentials['store_url'] ?? '';
        $accessToken = $this->credentials['access_token'] ?? '';
        $apiVersion = $this->credentials['api_version'] ?? '2025-07';

        if (empty($storeUrl) || empty($accessToken)) {
            session()->flash('error', 'Please provide both Store URL and Access Token before fetching store information.');

            return;
        }

        $this->isLoading = true;

        try {
            // Normalize store URL
            $storeUrl = rtrim($storeUrl, '/');
            if (! str_contains($storeUrl, '.myshopify.com')) {
                $storeUrl .= '.myshopify.com';
            }
            $storeUrl = str_replace(['http://', 'https://'], '', $storeUrl);

            // Update credentials with normalized URL
            $this->credentials['store_url'] = $storeUrl;

            $endpoint = "https://{$storeUrl}/admin/api/{$apiVersion}/shop.json";

            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'X-Shopify-Access-Token' => $accessToken,
                'Content-Type' => 'application/json',
            ])->timeout(10)->get($endpoint);

            if (! $response->successful()) {
                throw new \Exception("Shopify API returned error: {$response->status()}");
            }

            $shopData = $response->json('shop');

            if (! $shopData || ! isset($shopData['name'], $shopData['id'])) {
                throw new \Exception('Invalid response from Shopify API. Shop information not found.');
            }

            // Auto-populate shop information
            $this->credentials['shop_name'] = $shopData['name'];
            $this->credentials['shop_id'] = (string) $shopData['id'];
            $this->credentials['domain'] = $shopData['domain'] ?? '';

            // Update display name with shop info
            $this->displayName = $shopData['name'].' (Shopify)';

            // Store comprehensive auto-fetched data in settings
            $this->settings['auto_fetched_data'] = [
                'shop_name' => $shopData['name'],
                'shop_id' => $shopData['id'],
                'domain' => $shopData['domain'] ?? '',
                'currency' => $shopData['currency'] ?? '',
                'timezone' => $shopData['timezone'] ?? '',
                'plan_name' => $shopData['plan_name'] ?? '',
                'shop_owner' => $shopData['shop_owner'] ?? '',
                'email' => $shopData['email'] ?? '',
                'api_version' => $apiVersion,
                'fetched_at' => now()->toISOString(),
            ];

            session()->flash('success',
                '✅ Store information fetched successfully! '.
                "Shop: {$shopData['name']} (ID: {$shopData['id']}) | ".
                "Domain: {$shopData['domain']} | ".
                "Currency: {$shopData['currency']} | ".
                "Plan: {$shopData['plan_name']}"
            );

        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();

            // Provide helpful error messages
            if (str_contains($errorMessage, '401') || str_contains($errorMessage, 'Unauthorized')) {
                $errorMessage = 'Invalid access token. Please check your credentials.';
            } elseif (str_contains($errorMessage, '403') || str_contains($errorMessage, 'Forbidden')) {
                $errorMessage = 'Access denied. Please check your access token permissions.';
            } elseif (str_contains($errorMessage, '404') || str_contains($errorMessage, 'Not Found')) {
                $errorMessage = 'Store not found. Please verify your store URL is correct.';
            } elseif (str_contains($errorMessage, 'timeout') || str_contains($errorMessage, 'timed out')) {
                $errorMessage = 'Connection timed out. Please check your store URL and network connection.';
            }

            Log::error('❌ Shopify store info fetch failed', [
                'store_url' => $storeUrl,
                'access_token_prefix' => substr($accessToken, 0, 8).'...',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            session()->flash('error', "❌ Failed to fetch store information: {$errorMessage}");
        }

        $this->isLoading = false;
    }

    /**
     * 🏗️ CREATE MARKETPLACE CREDENTIALS
     */
    protected function createMarketplaceCredentials(string $type, array $credentials, array $settings = [], ?string $operator = null): \App\ValueObjects\MarketplaceCredentials
    {
        return new \App\ValueObjects\MarketplaceCredentials(
            type: $type,
            credentials: $credentials,
            settings: $settings,
            operator: $operator
        );
    }

    /**
     * 🔧 GET MARKETPLACE REGISTRY
     */
    protected function getMarketplaceRegistry(): MarketplaceRegistry
    {
        if (! isset($this->marketplaceRegistry)) {
            $this->marketplaceRegistry = app(MarketplaceRegistry::class);
        }

        return $this->marketplaceRegistry;
    }

    /**
     * 🎨 RENDER COMPONENT
     */
    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.marketplace.add-integration-wizard');
    }
}
