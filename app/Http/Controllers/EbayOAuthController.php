<?php

namespace App\Http\Controllers;

use App\Services\EbayOAuthService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EbayOAuthController extends Controller
{
    private EbayOAuthService $oauthService;

    public function __construct(EbayOAuthService $oauthService)
    {
        $this->oauthService = $oauthService;
    }

    /**
     * Redirect user to eBay for authorization
     */
    public function authorize(Request $request)
    {
        $request->validate([
            'account_name' => 'nullable|string|max:255',
            'scopes' => 'nullable|array',
        ]);

        try {
            $result = $this->oauthService->generateAuthorizationUrl(
                $request->input('account_name'),
                $request->input('scopes', [])
            );

            if (! $result['success']) {
                return back()->with('error', $result['error']);
            }

            Log::info('eBay OAuth authorization initiated', [
                'account_name' => $request->input('account_name'),
                'state' => $result['state'],
            ]);

            return redirect($result['authorization_url']);

        } catch (Exception $e) {
            Log::error('eBay OAuth authorization failed', [
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to initiate eBay authorization: '.$e->getMessage());
        }
    }

    /**
     * Handle eBay OAuth callback
     */
    public function callback(Request $request)
    {
        $code = $request->query('code');
        $state = $request->query('state');
        $error = $request->query('error');
        $errorDescription = $request->query('error_description');

        // Handle OAuth errors
        if ($error) {
            Log::warning('eBay OAuth callback error', [
                'error' => $error,
                'error_description' => $errorDescription,
            ]);

            $message = match ($error) {
                'access_denied' => 'Authorization was denied. Please try again if you want to connect your eBay account.',
                'invalid_request' => 'Invalid OAuth request. Please try again.',
                'invalid_client' => 'Invalid eBay application configuration. Please contact support.',
                'invalid_grant' => 'Invalid authorization grant. Please try again.',
                'unsupported_response_type' => 'Unsupported OAuth response type.',
                default => "eBay authorization failed: {$errorDescription}",
            };

            return redirect()->route('products.ebay-sync')
                ->with('error', $message);
        }

        // Validate required parameters
        if (! $code || ! $state) {
            return redirect()->route('products.ebay-sync')
                ->with('error', 'Missing required OAuth parameters from eBay callback.');
        }

        try {
            // Exchange code for tokens
            $tokenResult = $this->oauthService->exchangeCodeForToken($code, $state);

            if (! $tokenResult['success']) {
                return redirect()->route('products.ebay-sync')
                    ->with('error', $tokenResult['error']);
            }

            // Get account name from session (if provided during authorization)
            $accountName = session('ebay_oauth_account_name');

            // Create or update eBay account
            $accountResult = $this->oauthService->createOrUpdateAccount($tokenResult, $accountName);

            if (! $accountResult['success']) {
                return redirect()->route('products.ebay-sync')
                    ->with('error', $accountResult['error']);
            }

            $account = $accountResult['account'];
            $isNew = $accountResult['is_new'];

            $message = $isNew
                ? "eBay account '{$account->name}' connected successfully!"
                : "eBay account '{$account->name}' updated successfully!";

            Log::info('eBay OAuth callback completed successfully', [
                'account_id' => $account->id,
                'account_name' => $account->name,
                'is_new' => $isNew,
            ]);

            return redirect()->route('products.ebay-sync')
                ->with('success', $message);

        } catch (Exception $e) {
            Log::error('eBay OAuth callback processing failed', [
                'error' => $e->getMessage(),
                'code_present' => ! empty($code),
                'state_present' => ! empty($state),
            ]);

            return redirect()->route('products.ebay-sync')
                ->with('error', 'Failed to process eBay authorization: '.$e->getMessage());
        }
    }

    /**
     * Show account management page
     */
    public function accounts()
    {
        $accounts = \App\Models\EbayAccount::latest()->get();

        return view('ebay.accounts', compact('accounts'));
    }

    /**
     * Revoke an eBay account
     */
    public function revoke(Request $request, $accountId)
    {
        try {
            $account = \App\Models\EbayAccount::findOrFail($accountId);

            $result = $this->oauthService->revokeAccount($account);

            if ($result['success']) {
                return back()->with('success', "eBay account '{$account->name}' has been revoked.");
            } else {
                return back()->with('error', $result['error']);
            }

        } catch (Exception $e) {
            Log::error('eBay account revocation failed', [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to revoke eBay account: '.$e->getMessage());
        }
    }

    /**
     * Test account connection
     */
    public function test(Request $request, $accountId)
    {
        try {
            $account = \App\Models\EbayAccount::findOrFail($accountId);

            $tokenResult = $this->oauthService->getValidAccessToken($account);

            if ($tokenResult['success']) {
                // Refresh account data
                $account->refresh();

                return response()->json([
                    'success' => true,
                    'message' => 'eBay account connection is working!',
                    'account' => [
                        'name' => $account->name,
                        'status' => $account->status,
                        'last_used' => $account->last_used_at?->diffForHumans(),
                        'expires_at' => $account->token_expires_at?->diffForHumans(),
                    ],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => $tokenResult['error'],
                ], 400);
            }

        } catch (Exception $e) {
            Log::error('eBay account test failed', [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to test account: '.$e->getMessage(),
            ], 500);
        }
    }
}
