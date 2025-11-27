<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Transaction;
use Exception;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;

class TopUpService
{
    protected Client $client;
    protected int $maxRetries = 3;
    protected int $initialBackoffSeconds = 5;

    public function __construct()
    {
        $this->client = new Client();
    }

    /**
     * Deliver top-up to player
     *
     * @param Order $order Order instance
     * @return array Delivery result
     */
    public function deliverTopUp(Order $order): array
    {
        try {
            Log::info('Initiating top-up delivery', [
                'order_id' => $order->order_id,
                'player_uid' => $order->player_uid,
                'total_amount' => $order->total,
            ]);

            // Build delivery payload
            $payload = $this->buildDeliveryPayload($order);

            // Attempt delivery with retries
            $result = $this->attemptDeliveryWithRetry($payload);

            if (!$result['success']) {
                return $result;
            }

            // Update order and transaction status
            $this->updateOrderStatus($order, 'delivered', $result['transaction_id']);

            Log::info('Top-up successfully delivered', [
                'order_id' => $order->order_id,
                'provider_transaction_id' => $result['transaction_id'],
            ]);

            return [
                'success' => true,
                'message' => 'Top-up delivered successfully',
                'provider_transaction_id' => $result['transaction_id'],
                'delivery_time' => now()->toIso8601String(),
            ];

        } catch (Exception $e) {
            Log::error('Top-up delivery error', [
                'order_id' => $order->order_id,
                'error' => $e->getMessage(),
            ]);

            // Mark as failed after max retries
            $this->updateOrderStatus($order, 'failed', null, $e->getMessage());

            return [
                'success' => false,
                'message' => 'Failed to deliver top-up: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Build payload for top-up provider API
     *
     * @param Order $order Order instance
     * @return array API payload
     */
    private function buildDeliveryPayload(Order $order): array
    {
        $items = [];
        $totalQuantity = 0;

        foreach ($order->items as $item) {
            $items[] = [
                'product_id' => $item->product_id,
                'name' => $item->name,
                'game' => $item->game,
                'quantity' => $item->quantity,
                'price' => $item->price,
            ];
            $totalQuantity += $item->quantity;
        }

        return [
            'api_key' => config('services.topup_provider.api_key'),
            'merchant_id' => config('services.topup_provider.merchant_id'),
            'order_id' => $order->order_id,
            'player_uid' => $order->player_uid,
            'player_nickname' => $order->player_nickname,
            'game' => $order->items->first()?->game ?? 'General',
            'amount' => $order->total,
            'currency' => 'USD',
            'items' => $items,
            'items_count' => count($items),
            'total_quantity' => $totalQuantity,
            'email' => $order->email,
            'phone' => $order->phone,
            'webhook_url' => config('app.url') . '/webhooks/topup-delivery',
            'metadata' => [
                'order_created_at' => $order->created_at->toIso8601String(),
                'customer_country' => request()->header('CF-IPCountry', 'US'),
            ],
        ];
    }

    /**
     * Attempt delivery with exponential backoff retry logic
     *
     * @param array $payload Delivery payload
     * @param int $attempt Current attempt number
     * @return array Delivery result
     * @throws Exception
     */
    private function attemptDeliveryWithRetry(array $payload, int $attempt = 1): array
    {
        try {
            $apiUrl = config('services.topup_provider.url');
            $timeout = config('services.topup_provider.timeout', 30);

            Log::info('Attempting top-up delivery', [
                'attempt' => $attempt,
                'order_id' => $payload['order_id'],
            ]);

            $response = $this->client->post("{$apiUrl}/delivery/topup", [
                'json' => $payload,
                'timeout' => $timeout,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'DaveTopup-Checkout/1.0',
                    'X-Request-ID' => uniqid('req_', true),
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $result = json_decode($response->getBody(), true);

            // Success responses
            if ($statusCode >= 200 && $statusCode < 300) {
                if ($result['success'] ?? false) {
                    return [
                        'success' => true,
                        'transaction_id' => $result['transaction_id'] ?? $result['id'] ?? null,
                        'message' => $result['message'] ?? 'Delivery successful',
                    ];
                }
            }

            // Retryable errors (5xx, timeouts, rate limits)
            if ($statusCode >= 500 || $statusCode === 429) {
                if ($attempt < $this->maxRetries) {
                    $backoff = $this->initialBackoffSeconds * pow(2, $attempt - 1);
                    Log::warning('Top-up delivery failed, retrying', [
                        'attempt' => $attempt,
                        'status_code' => $statusCode,
                        'backoff_seconds' => $backoff,
                        'order_id' => $payload['order_id'],
                    ]);

                    sleep($backoff);
                    return $this->attemptDeliveryWithRetry($payload, $attempt + 1);
                }
            }

            // Non-retryable errors
            $errorMessage = $result['message'] ?? $result['error'] ?? 'Unknown error';
            throw new Exception("Top-up provider error: {$errorMessage} (HTTP {$statusCode})");

        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            // Connection timeout - retry
            if ($attempt < $this->maxRetries) {
                $backoff = $this->initialBackoffSeconds * pow(2, $attempt - 1);
                Log::warning('Top-up delivery connection timeout, retrying', [
                    'attempt' => $attempt,
                    'backoff_seconds' => $backoff,
                ]);
                sleep($backoff);
                return $this->attemptDeliveryWithRetry($payload, $attempt + 1);
            }

            throw new Exception("Failed to connect to top-up provider after {$this->maxRetries} attempts");

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            // Request error
            throw new Exception("Top-up provider request failed: " . $e->getMessage());

        } catch (Exception $e) {
            // Unexpected error
            throw $e;
        }
    }

    /**
     * Update order delivery status
     *
     * @param Order $order Order instance
     * @param string $status New status
     * @param string|null $providerTransactionId Provider transaction ID
     * @param string|null $errorMessage Error message if failed
     * @return void
     */
    private function updateOrderStatus(
        Order $order,
        string $status,
        ?string $providerTransactionId = null,
        ?string $errorMessage = null
    ): void {
        try {
            // Update order
            $order->update(['status' => $status]);

            // Update transaction with delivery info
            $transaction = $order->transactions()->first();
            if ($transaction) {
                $metadata = $transaction->metadata ?? [];
                $metadata['delivery_status'] = $status;
                if ($providerTransactionId) {
                    $metadata['provider_transaction_id'] = $providerTransactionId;
                }
                if ($errorMessage) {
                    $metadata['delivery_error'] = $errorMessage;
                }
                $metadata['delivery_attempted_at'] = now()->toIso8601String();

                $transaction->update([
                    'status' => $status,
                    'metadata' => $metadata,
                ]);
            }

            Log::info('Order status updated', [
                'order_id' => $order->order_id,
                'status' => $status,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to update order status', [
                'order_id' => $order->order_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Process webhook from top-up provider confirming delivery
     *
     * @param array $payload Webhook payload
     * @return array Processing result
     */
    public function processDeliveryWebhook(array $payload): array
    {
        try {
            // Verify webhook signature
            if (!$this->verifyWebhookSignature($payload)) {
                Log::warning('Invalid webhook signature for top-up delivery');
                return [
                    'success' => false,
                    'message' => 'Invalid signature',
                ];
            }

            $orderId = $payload['order_id'] ?? null;
            $status = $payload['status'] ?? 'unknown';

            if (!$orderId) {
                return [
                    'success' => false,
                    'message' => 'Missing order_id',
                ];
            }

            // Find order by order_id (not database ID)
            $order = Order::where('order_id', $orderId)->first();

            if (!$order) {
                Log::warning('Top-up webhook for unknown order', ['order_id' => $orderId]);
                return [
                    'success' => false,
                    'message' => 'Order not found',
                ];
            }

            // Prevent duplicate processing
            if ($order->status === 'delivered') {
                Log::info('Top-up webhook already processed', ['order_id' => $orderId]);
                return [
                    'success' => true,
                    'message' => 'Already processed',
                ];
            }

            // Update status based on webhook
            $this->updateOrderStatus(
                $order,
                $status === 'success' ? 'delivered' : 'failed',
                $payload['transaction_id'] ?? null,
                $payload['error'] ?? null
            );

            Log::info('Top-up webhook processed', [
                'order_id' => $orderId,
                'status' => $status,
            ]);

            return [
                'success' => true,
                'message' => 'Webhook processed successfully',
            ];

        } catch (Exception $e) {
            Log::error('Error processing top-up webhook', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verify webhook signature from top-up provider
     *
     * @param array $payload Webhook payload
     * @return bool Signature is valid
     */
    private function verifyWebhookSignature(array $payload): bool
    {
        $signature = request()->header('X-Topup-Signature');
        $timestamp = request()->header('X-Topup-Timestamp');

        if (!$signature || !$timestamp) {
            return false;
        }

        // Prevent replay attacks
        if (abs(time() - intval($timestamp)) > 300) {
            return false;
        }

        // Create expected signature
        $secret = config('services.topup_provider.webhook_secret');
        $data = $timestamp . '.' . json_encode($payload, JSON_UNESCAPED_SLASHES);
        $expected = hash_hmac('sha256', $data, $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * Get delivery status for an order
     *
     * @param Order $order Order instance
     * @return array Status info
     */
    public function getDeliveryStatus(Order $order): array
    {
        try {
            $transaction = $order->transactions()->first();

            if (!$transaction) {
                return [
                    'success' => false,
                    'message' => 'No transaction found',
                ];
            }

            $metadata = $transaction->metadata ?? [];

            return [
                'success' => true,
                'order_id' => $order->order_id,
                'status' => $order->status,
                'delivery_status' => $metadata['delivery_status'] ?? 'pending',
                'provider_transaction_id' => $metadata['provider_transaction_id'] ?? null,
                'delivery_attempted_at' => $metadata['delivery_attempted_at'] ?? null,
                'delivery_error' => $metadata['delivery_error'] ?? null,
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Manually retry delivery for a failed order (admin only)
     *
     * @param Order $order Order instance
     * @return array Retry result
     */
    public function retryDelivery(Order $order): array
    {
        try {
            if ($order->status === 'delivered') {
                return [
                    'success' => false,
                    'message' => 'Order already delivered',
                ];
            }

            Log::info('Manual delivery retry initiated', ['order_id' => $order->order_id]);

            $result = $this->deliverTopUp($order);

            return $result;

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
