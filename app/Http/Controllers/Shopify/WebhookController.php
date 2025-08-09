<?php

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use App\Services\Shopify\API\ShopifyWebhookService;
use App\Jobs\ProcessShopifyWebhookJob;
use App\Models\ShopifyWebhookLog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

/**
 * 🎭 LEGENDARY SHOPIFY WEBHOOK CONTROLLER 🎭
 * 
 * Handles incoming Shopify webhooks with MAXIMUM SASS and security!
 * Because webhook security should be as tight as my corset, darling! 💅
 */
class WebhookController extends Controller
{
    public function __construct(
        private ShopifyWebhookService $webhookService
    ) {}

    /**
     * 🚀 LEGENDARY webhook handler - the main event!
     * 
     * Handles ALL Shopify webhook topics with security verification
     * and fabulous queue processing
     */
    public function handle(Request $request): Response
    {
        $startTime = microtime(true);
        $webhookId = uniqid('webhook_', true);
        
        Log::info("🎭 LEGENDARY webhook received!", [
            'webhook_id' => $webhookId,
            'topic' => $request->header('X-Shopify-Topic'),
            'shop_domain' => $request->header('X-Shopify-Shop-Domain'),
            'content_length' => $request->header('Content-Length'),
            'user_agent' => $request->header('User-Agent')
        ]);

        try {
            // 🔒 SECURITY FIRST - Verify webhook authenticity
            if (!$this->verifyWebhookSignature($request)) {
                Log::warning("🚨 INVALID webhook signature - someone's trying to crash our party!", [
                    'webhook_id' => $webhookId,
                    'ip' => $request->ip(),
                    'topic' => $request->header('X-Shopify-Topic')
                ]);
                
                return response('Unauthorized', 401);
            }

            // 📝 Extract webhook details
            $topic = $request->header('X-Shopify-Topic');
            $shopDomain = $request->header('X-Shopify-Shop-Domain');
            $payload = $request->getContent();
            $data = json_decode($payload, true);

            // 🎪 Log this FABULOUS webhook
            $webhookLog = ShopifyWebhookLog::createFromWebhook($topic, $data, [
                'webhook_id' => $webhookId,
                'shop_domain' => $shopDomain,
                'verified' => true,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
                'processing_time' => null // Will be updated after processing
            ]);

            // 🚀 Queue for LEGENDARY processing
            ProcessShopifyWebhookJob::dispatch($webhookLog->id, $topic, $data, [
                'webhook_id' => $webhookId,
                'shop_domain' => $shopDomain,
                'received_at' => now()->toISOString()
            ])->onQueue('shopify-webhooks');

            $processingTime = round((microtime(true) - $startTime) * 1000, 2);
            
            Log::info("✨ Webhook queued for LEGENDARY processing", [
                'webhook_id' => $webhookId,
                'topic' => $topic,
                'processing_time_ms' => $processingTime,
                'queue' => 'shopify-webhooks'
            ]);

            // Update processing time
            $webhookLog->update([
                'metadata->processing_time' => $processingTime,
                'status' => 'queued'
            ]);

            return response('OK', 200);

        } catch (\Exception $e) {
            $processingTime = round((microtime(true) - $startTime) * 1000, 2);
            
            Log::error("💥 Webhook processing DRAMA occurred!", [
                'webhook_id' => $webhookId,
                'error' => $e->getMessage(),
                'processing_time_ms' => $processingTime,
                'trace' => $e->getTraceAsString()
            ]);

            // Log failed webhook
            if (isset($topic)) {
                ShopifyWebhookLog::createFromWebhook($topic ?? 'unknown', [], [
                    'webhook_id' => $webhookId,
                    'verified' => false,
                    'error' => $e->getMessage(),
                    'processing_time' => $processingTime,
                    'status' => 'failed'
                ]);
            }

            return response('Internal Server Error', 500);
        }
    }

    /**
     * 🔒 LEGENDARY signature verification
     * 
     * Verifies webhook authenticity using HMAC-SHA256
     * Because we don't let just ANYONE into our fabulous party!
     */
    private function verifyWebhookSignature(Request $request): bool
    {
        $signature = $request->header('X-Shopify-Hmac-Sha256');
        $payload = $request->getContent();
        $webhookSecret = config('services.shopify.webhook_secret');

        if (!$signature || !$webhookSecret) {
            Log::warning("🚨 Missing signature or webhook secret - security breach attempt!");
            return false;
        }

        $expectedSignature = base64_encode(
            hash_hmac('sha256', $payload, $webhookSecret, true)
        );

        $isValid = hash_equals($expectedSignature, $signature);
        
        if (!$isValid) {
            Log::warning("🚨 Signature mismatch - someone's being SHADY!", [
                'expected_length' => strlen($expectedSignature),
                'received_length' => strlen($signature),
                'payload_length' => strlen($payload)
            ]);
        }

        return $isValid;
    }

    /**
     * 📊 LEGENDARY webhook health endpoint
     * 
     * Returns webhook system health for monitoring
     */
    public function health(): Response
    {
        $health = $this->webhookService->getWebhookHealthStatus();
        
        return response()->json([
            'status' => 'LEGENDARY',
            'webhook_system' => 'FABULOUS',
            'health' => $health,
            'sass_level' => 'MAXIMUM',
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * 🎪 LEGENDARY webhook registration endpoint
     * 
     * Registers webhooks with Shopify (admin only)
     */
    public function register(Request $request): Response
    {
        $request->validate([
            'topics' => 'required|array',
            'topics.*' => 'required|string',
            'callback_url' => 'required|url'
        ]);

        try {
            $result = $this->webhookService->registerWebhooks(
                $request->input('topics'),
                $request->input('callback_url')
            );

            Log::info("🎭 LEGENDARY webhooks registered!", [
                'topics' => $request->input('topics'),
                'callback_url' => $request->input('callback_url'),
                'result' => $result
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Webhooks registered with LEGENDARY style!',
                'data' => $result,
                'sass_level' => 'MAXIMUM'
            ]);

        } catch (\Exception $e) {
            Log::error("💥 Webhook registration DRAMA!", [
                'error' => $e->getMessage(),
                'topics' => $request->input('topics')
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Webhook registration failed - but we still look FABULOUS!',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}