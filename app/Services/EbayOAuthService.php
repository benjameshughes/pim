<?php

namespace App\Services;

use App\Models\EbayAccount;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class EbayOAuthService
{
    private Client $client;
    private array $config;
    private string $environment;
    private string $baseUrl;

    public function __construct()
    {
        $this->environment = config('services.ebay.environment', 'SANDBOX');
        $this->config = [
            'client_id' => config('services.ebay.client_id'),
            'client_secret' => config('services.ebay.client_secret'),
            'redirect_uri' => config('services.ebay.redirect_uri'),
            'dev_id' => config('services.ebay.dev_id'),
        ];
        
        $this->baseUrl = $this->environment === 'PRODUCTION' 
            ? 'https://api.ebay.com' 
            : 'https://api.sandbox.ebay.com';

        $this->client = new Client([
            'timeout' => 30,
            'verify' => true,
        ]);
    }

    /**
     * Generate the authorization URL for user consent
     */
    public function generateAuthorizationUrl(string $accountName = null, array $scopes = []): array
    {
        if (empty($this->config['client_id']) || empty($this->config['redirect_uri'])) {
            return [
                'success' => false,
                'error' => 'eBay OAuth configuration incomplete. Missing client_id or redirect_uri.',
            ];
        }

        // Default scopes for inventory management
        if (empty($scopes)) {
            $scopes = [
                'https://api.ebay.com/oauth/api_scope/sell.inventory',
                'https://api.ebay.com/oauth/api_scope/sell.inventory.readonly',
                'https://api.ebay.com/oauth/api_scope/sell.account',
                'https://api.ebay.com/oauth/api_scope/sell.account.readonly',
            ];
        }

        // Generate state parameter for CSRF protection
        $state = Str::random(40);
        
        // Store state in session for validation
        session(['ebay_oauth_state' => $state]);
        
        // Store account name if provided
        if ($accountName) {
            session(['ebay_oauth_account_name' => $accountName]);
        }

        $authUrl = $this->environment === 'PRODUCTION'
            ? 'https://auth.ebay.com/oauth2/authorize'
            : 'https://auth.sandbox.ebay.com/oauth2/authorize';

        $params = [
            'client_id' => $this->config['client_id'],
            'redirect_uri' => $this->config['redirect_uri'],
            'response_type' => 'code',
            'state' => $state,
            'scope' => implode(' ', $scopes),
            'prompt' => 'login', // Force user to login even if they have existing session
        ];

        $url = $authUrl . '?' . http_build_query($params);

        return [
            'success' => true,
            'authorization_url' => $url,
            'state' => $state,
            'scopes' => $scopes,
        ];
    }

    /**
     * Exchange authorization code for access token
     */
    public function exchangeCodeForToken(string $code, string $state): array
    {
        // Validate state parameter
        if ($state !== session('ebay_oauth_state')) {
            return [
                'success' => false,
                'error' => 'Invalid state parameter. Possible CSRF attack.',
            ];
        }

        if (empty($this->config['client_id']) || empty($this->config['client_secret'])) {
            return [
                'success' => false,
                'error' => 'eBay OAuth configuration incomplete.',
            ];
        }

        try {
            $response = $this->client->post("{$this->baseUrl}/identity/v1/oauth2/token", [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Authorization' => 'Basic ' . base64_encode(
                        $this->config['client_id'] . ':' . $this->config['client_secret']
                    ),
                ],
                'form_params' => [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => $this->config['redirect_uri'],
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            // Clear session data
            session()->forget(['ebay_oauth_state', 'ebay_oauth_account_name']);

            return [
                'success' => true,
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'],
                'expires_in' => $data['expires_in'],
                'token_type' => $data['token_type'],
                'scope' => $data['scope'] ?? '',
            ];

        } catch (RequestException $e) {
            Log::error('eBay OAuth token exchange failed', [
                'error' => $e->getMessage(),
                'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null,
            ]);

            return [
                'success' => false,
                'error' => 'Failed to exchange authorization code: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Refresh an expired access token
     */
    public function refreshToken(EbayAccount $account): array
    {
        if (empty($account->refresh_token)) {
            return [
                'success' => false,
                'error' => 'No refresh token available for this account.',
            ];
        }

        try {
            $response = $this->client->post("{$this->baseUrl}/identity/v1/oauth2/token", [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Authorization' => 'Basic ' . base64_encode(
                        $this->config['client_id'] . ':' . $this->config['client_secret']
                    ),
                ],
                'form_params' => [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $account->refresh_token,
                    'scope' => implode(' ', $account->scopes ?? []),
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            // Update the account with new tokens
            $account->updateTokens($data);

            Log::info('eBay token refreshed successfully', [
                'account_id' => $account->id,
                'account_name' => $account->name,
            ]);

            return [
                'success' => true,
                'access_token' => $data['access_token'],
                'expires_in' => $data['expires_in'],
            ];

        } catch (RequestException $e) {
            Log::error('eBay token refresh failed', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
                'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null,
            ]);

            // Mark account as expired if refresh fails
            $account->update(['status' => 'expired']);

            return [
                'success' => false,
                'error' => 'Failed to refresh token: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get user info from eBay (to get eBay user ID)
     */
    public function getUserInfo(string $accessToken): array
    {
        try {
            $response = $this->client->get("{$this->baseUrl}/commerce/identity/v1/user/", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return [
                'success' => true,
                'user_id' => $data['userId'] ?? null,
                'username' => $data['username'] ?? null,
                'data' => $data,
            ];

        } catch (RequestException $e) {
            Log::error('eBay get user info failed', [
                'error' => $e->getMessage(),
                'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null,
            ]);

            return [
                'success' => false,
                'error' => 'Failed to get user info: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Create or update eBay account from OAuth response
     */
    public function createOrUpdateAccount(array $tokenData, string $accountName = null): array
    {
        try {
            // Get user info to obtain eBay user ID
            $userInfo = $this->getUserInfo($tokenData['access_token']);
            
            if (!$userInfo['success']) {
                return [
                    'success' => false,
                    'error' => 'Failed to get user information: ' . $userInfo['error'],
                ];
            }

            $ebayUserId = $userInfo['user_id'];
            $username = $userInfo['username'];

            // Use provided account name or generate from username
            $finalAccountName = $accountName ?: ($username . ' (' . $this->environment . ')');

            // Parse scopes
            $scopes = !empty($tokenData['scope']) 
                ? explode(' ', $tokenData['scope'])
                : [];

            // Create or update account
            $account = EbayAccount::updateOrCreate(
                [
                    'ebay_user_id' => $ebayUserId,
                    'environment' => $this->environment,
                ],
                [
                    'name' => $finalAccountName,
                    'access_token' => $tokenData['access_token'],
                    'refresh_token' => $tokenData['refresh_token'],
                    'expires_in' => $tokenData['expires_in'],
                    'token_expires_at' => now()->addSeconds($tokenData['expires_in']),
                    'scopes' => $scopes,
                    'status' => 'active',
                    'oauth_data' => array_merge($tokenData, $userInfo['data']),
                ]
            );

            Log::info('eBay account created/updated successfully', [
                'account_id' => $account->id,
                'account_name' => $account->name,
                'ebay_user_id' => $ebayUserId,
            ]);

            return [
                'success' => true,
                'account' => $account,
                'is_new' => $account->wasRecentlyCreated,
            ];

        } catch (Exception $e) {
            Log::error('Failed to create/update eBay account', [
                'error' => $e->getMessage(),
                'token_data' => array_keys($tokenData), // Don't log sensitive data
            ]);

            return [
                'success' => false,
                'error' => 'Failed to create account: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get valid access token for account (with auto-refresh)
     */
    public function getValidAccessToken(EbayAccount $account): array
    {
        if ($account->isActive()) {
            $account->markAsUsed();
            return [
                'success' => true,
                'access_token' => $account->access_token,
            ];
        }

        if ($account->needsRefresh()) {
            return $this->refreshToken($account);
        }

        return [
            'success' => false,
            'error' => 'Account tokens are invalid and cannot be refreshed. Re-authorization required.',
        ];
    }

    /**
     * Revoke account tokens
     */
    public function revokeAccount(EbayAccount $account): array
    {
        try {
            // Revoke the token with eBay
            $this->client->post("{$this->baseUrl}/identity/v1/oauth2/revoke", [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Authorization' => 'Basic ' . base64_encode(
                        $this->config['client_id'] . ':' . $this->config['client_secret']
                    ),
                ],
                'form_params' => [
                    'token' => $account->access_token,
                ],
            ]);

            // Update account status
            $account->update([
                'status' => 'revoked',
                'access_token' => null,
                'refresh_token' => null,
            ]);

            return [
                'success' => true,
                'message' => 'Account tokens revoked successfully.',
            ];

        } catch (RequestException $e) {
            Log::error('eBay token revocation failed', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            // Still update local status even if API call fails
            $account->update(['status' => 'revoked']);

            return [
                'success' => false,
                'error' => 'Failed to revoke tokens: ' . $e->getMessage(),
            ];
        }
    }
}