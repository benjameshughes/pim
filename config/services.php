<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1/messages'),
    ],

    'mirakl' => [
        'base_url' => env('MIRAKL_BASE_URL', 'https://miraklconnect.com/api'),
        'client_id' => env('MIRAKL_CLIENT_ID'),
        'client_secret' => env('MIRAKL_CLIENT_SECRET'),
        'audience' => env('MIRAKL_AUDIENCE'),
        'seller_id' => env('MIRAKL_SELLER_ID'),
    ],

    'shopify' => [
        'store_url' => env('SHOPIFY_STORE_URL'), // e.g., your-store.myshopify.com (without https://)
        'access_token' => env('SHOPIFY_ACCESS_TOKEN'), // Admin API access token from custom app
        'api_version' => env('SHOPIFY_API_VERSION', '2024-07'),
        'api_key' => env('SHOPIFY_API_KEY'), // From custom app (optional, for OAuth apps)
        'api_secret' => env('SHOPIFY_API_SECRET'), // From custom app (optional, for OAuth apps)
    ],

    'ebay' => [
        'environment' => env('EBAY_ENVIRONMENT', 'SANDBOX'), // SANDBOX or PRODUCTION
        'client_id' => env('EBAY_CLIENT_ID'), // App ID from eBay Developer Account
        'client_secret' => env('EBAY_CLIENT_SECRET'), // Client Secret from eBay Developer Account
        'dev_id' => env('EBAY_DEV_ID'), // Dev ID from eBay Developer Account
        'redirect_uri' => env('EBAY_REDIRECT_URI', 'http://localhost:8001/sync/ebay/oauth/callback'), // OAuth redirect URI
        // Business Policies (required for listings)
        'fulfillment_policy_id' => env('EBAY_FULFILLMENT_POLICY_ID'),
        'payment_policy_id' => env('EBAY_PAYMENT_POLICY_ID'),
        'return_policy_id' => env('EBAY_RETURN_POLICY_ID'),
        'location_key' => env('EBAY_LOCATION_KEY', 'default_location'),
    ],

];
