<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Transaction;
use App\Models\WebhookLog;
use App\Services\TopUpService;
use App\Services\StripeConnectService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Exception;

class WebhookController extends Controller
{
    /**
     * Handle Stripe webhooks
     */
    public function stripe(Request $request): JsonResponse
    {
        try {
            $signature = $request->header('Stripe-Signature');
            $payload = $request->getContent();
            $secret = config('services.stripe.webhook_secret');

            // Verify webhook signature
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $signature,
                $secret
            );

            // Log webhook
            WebhookLog::create([
                'provider' => 'stripe',
                'event_type' => $event->type,
                'payload' => $event->data,
                'response_status' => 200,
                'processed_at' => now(),
            ]);

            // Handle event
            match ($event->type) {
                'payment_intent.succeeded' => $this->handleStripeSuccess($event),
                'payment_intent.payment_failed' => $this->handleStripeFailed($event),
                'charge.refunded' => $this->handleStripeRefunded($event),
                'transfer.created' => $this->handleTransferCreated($event),
                'transfer.failed' => $this->handleTransferFailed($event),
                'transfer.reversed' => $this->handleTransferReversed($event),
                default => Log::info("Unhandled Stripe event: {$event->type}"),
            };

            return response()->json(['success' => true], 200);

        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::warning('Stripe webhook signature verification failed', ['error' => $e->getMessage()]);
            WebhookLog::create([
                'provider' => 'stripe',
                'event_type' => 'signature_verification_failed',
                'payload' => $request->all(),
                'response_status' => 403,
            ]);
            return response()->json(['error' => 'Signature verification failed'], 403);

        } catch (Exception $e) {
            Log::error('Stripe webhook error', ['error' => $e->getMessage()]);
            WebhookLog::create([
                'provider' => 'stripe',
                'event_type' => 'error',
                'payload' => $request->all(),
                'response_status' => 500,
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Handle Stripe payment_intent.succeeded event
     */
    private function handleStripeSuccess($event): void
    {
        $intent = $event->data->object;
        $metadata = $intent->metadata;
        $orderId = $metadata->order_id ?? null;

        if (!$orderId) {
            Log::warning('Stripe success webhook missing order_id', ['intent_id' => $intent->id]);
            return;
        }

        // Find order
        $order = Order::where('order_id', $orderId)->first();
        if (!$order) {
            Log::warning('Stripe success webhook: order not found', ['order_id' => $orderId]);
            return;
        }

        // Prevent duplicate processing
        if ($order->status === 'payment_confirmed') {
            Log::info('Stripe payment already confirmed', ['order_id' => $orderId]);
            return;
        }

        // Create or update transaction
        $transaction = $order->transactions()->firstOrCreate(
            ['transaction_id' => $intent->id],
            [
                'payment_method' => 'card',
                'amount' => $order->total,
                'currency' => strtoupper($intent->currency),
                'status' => 'completed',
                'metadata' => [
                    'stripe_intent_id' => $intent->id,
                    'charge_id' => $intent->charges->data[0]->id ?? null,
                    'receipt_email' => $intent->receipt_email ?? null,
                ],
            ]
        );

        // Update order status
        $order->update(['status' => 'payment_confirmed']);

        // Dispatch delivery job
        if (config('services.feature.async_delivery')) {
            dispatch(new \App\Jobs\DeliverTopUp($order->id));
        } else {
            // Synchronous delivery
            $topUpService = app(TopUpService::class);
            $topUpService->deliverTopUp($order);
        }

        Log::info('Stripe payment confirmed', [
            'order_id' => $orderId,
            'stripe_intent_id' => $intent->id,
        ]);
    }

    /**
     * Handle Stripe payment_intent.payment_failed event
     */
    private function handleStripeFailed($event): void
    {
        $intent = $event->data->object;
        $metadata = $intent->metadata;
        $orderId = $metadata->order_id ?? null;

        if (!$orderId) {
            Log::warning('Stripe failed webhook missing order_id', ['intent_id' => $intent->id]);
            return;
        }

        $order = Order::where('order_id', $orderId)->first();
        if (!$order) {
            Log::warning('Stripe failed webhook: order not found', ['order_id' => $orderId]);
            return;
        }

        // Update transaction status
        $order->transactions()->updateOrCreate(
            ['transaction_id' => $intent->id],
            [
                'status' => 'failed',
                'metadata' => [
                    'stripe_intent_id' => $intent->id,
                    'error' => $intent->last_payment_error?->message ?? 'Payment failed',
                ],
            ]
        );

        // Update order status
        $order->update(['status' => 'failed']);

        Log::warning('Stripe payment failed', [
            'order_id' => $orderId,
            'error' => $intent->last_payment_error?->message ?? 'Unknown error',
        ]);
    }

    /**
     * Handle Stripe charge.refunded event
     */
    private function handleStripeRefunded($event): void
    {
        $charge = $event->data->object;

        Log::info('Stripe refund processed', [
            'charge_id' => $charge->id,
            'refund_amount' => $charge->amount_refunded,
        ]);

        // Find transaction by charge ID
        $transaction = Transaction::where('metadata->charge_id', $charge->id)->first();
        if (!$transaction) {
            Log::warning('Stripe refund: charge not found', ['charge_id' => $charge->id]);
            return;
        }

        $order = $transaction->order;
        if ($charge->amount_refunded === $charge->amount) {
            // Full refund
            $order->update(['status' => 'refunded']);
        }

        // Update transaction metadata
        $metadata = $transaction->metadata ?? [];
        $metadata['refunded_at'] = now()->toIso8601String();
        $metadata['refund_amount'] = $charge->amount_refunded;
        $transaction->update(['metadata' => $metadata]);
    }

    /**
     * Handle PayPal webhooks
     */
    public function paypal(Request $request): JsonResponse
    {
        try {
            $headers = [
                'transmission_id' => $request->header('PayPal-Transmission-Id'),
                'transmission_time' => $request->header('PayPal-Transmission-Time'),
                'cert_url' => $request->header('PayPal-Cert-Url'),
                'auth_algo' => $request->header('PayPal-Auth-Algo'),
                'transmission_sig' => $request->header('PayPal-Transmission-Sig'),
            ];

            // Verify webhook (simplified - use PayPal SDK in production)
            if (!$this->verifyPayPalSignature($request->all(), $headers)) {
                Log::warning('PayPal webhook signature verification failed');
                return response()->json(['error' => 'Signature verification failed'], 403);
            }

            $event = $request->input();

            WebhookLog::create([
                'provider' => 'paypal',
                'event_type' => $event['event_type'] ?? 'unknown',
                'payload' => $event,
                'response_status' => 200,
                'processed_at' => now(),
            ]);

            // Handle event type
            match ($event['event_type'] ?? null) {
                'CHECKOUT.ORDER.COMPLETED' => $this->handlePayPalOrderCompleted($event),
                'PAYMENT.CAPTURE.COMPLETED' => $this->handlePayPalCaptureCompleted($event),
                default => Log::info("Unhandled PayPal event: {$event['event_type']}"),
            };

            return response()->json(['success' => true], 200);

        } catch (Exception $e) {
            Log::error('PayPal webhook error', ['error' => $e->getMessage()]);
            WebhookLog::create([
                'provider' => 'paypal',
                'event_type' => 'error',
                'payload' => $request->all(),
                'response_status' => 500,
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Handle PayPal CHECKOUT.ORDER.COMPLETED event
     */
    private function handlePayPalOrderCompleted($event): void
    {
        $resource = $event['resource'] ?? [];
        $paypalOrderId = $resource['id'] ?? null;
        $referenceId = $resource['purchase_units'][0]['reference_id'] ?? null;

        if (!$referenceId) {
            Log::warning('PayPal order event missing reference_id');
            return;
        }

        $order = Order::where('order_id', $referenceId)->first();
        if (!$order) {
            Log::warning('PayPal order: order not found', ['order_id' => $referenceId]);
            return;
        }

        Log::info('PayPal order completed', [
            'order_id' => $referenceId,
            'paypal_order_id' => $paypalOrderId,
        ]);
    }

    /**
     * Handle PayPal PAYMENT.CAPTURE.COMPLETED event
     */
    private function handlePayPalCaptureCompleted($event): void
    {
        $resource = $event['resource'] ?? [];
        $captureId = $resource['id'] ?? null;

        // Find order by PayPal capture ID
        $transaction = Transaction::where('metadata->paypal_capture_id', $captureId)->first();
        if (!$transaction) {
            Log::warning('PayPal capture: transaction not found', ['capture_id' => $captureId]);
            return;
        }

        $order = $transaction->order;

        // Update order and transaction
        $order->update(['status' => 'payment_confirmed']);
        $transaction->update(['status' => 'completed']);

        // Dispatch delivery
        if (config('services.feature.async_delivery')) {
            dispatch(new \App\Jobs\DeliverTopUp($order->id));
        } else {
            $topUpService = app(TopUpService::class);
            $topUpService->deliverTopUp($order);
        }

        Log::info('PayPal payment captured', [
            'order_id' => $order->order_id,
            'capture_id' => $captureId,
        ]);
    }

    /**
     * Verify PayPal webhook signature (simplified)
     */
    private function verifyPayPalSignature(array $body, array $headers): bool
    {
        // Full verification requires validating against PayPal certificates
        // For production, use PayPal SDK's verification
        // This is a placeholder - implement full verification
        return !empty($headers['transmission_sig']);
    }

    /**
     * Handle Binance Pay webhooks
     */
    public function binance(Request $request): JsonResponse
    {
        try {
            $signature = $request->header('BinancePay-Signature');
            $payload = $request->getContent();

            // Verify signature
            $secret = config('services.binance.secret_key');
            $expected = base64_encode(
                hash_hmac('sha256', $payload, $secret, true)
            );

            if (!hash_equals($expected, $signature ?? '')) {
                Log::warning('Binance webhook signature verification failed');
                return response()->json(['error' => 'Signature verification failed'], 403);
            }

            $event = json_decode($payload, true);

            WebhookLog::create([
                'provider' => 'binance',
                'event_type' => 'payment_status_update',
                'payload' => $event,
                'response_status' => 200,
                'processed_at' => now(),
            ]);

            $this->handleBinancePaymentUpdate($event);

            return response()->json(['success' => true], 200);

        } catch (Exception $e) {
            Log::error('Binance webhook error', ['error' => $e->getMessage()]);
            WebhookLog::create([
                'provider' => 'binance',
                'event_type' => 'error',
                'payload' => $request->all(),
                'response_status' => 500,
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Handle Binance payment status update
     */
    private function handleBinancePaymentUpdate($event): void
    {
        $orderId = $event['merchantTradeNo'] ?? null;
        $status = $event['status'] ?? null;

        if (!$orderId || !$status) {
            Log::warning('Binance webhook missing required fields');
            return;
        }

        $order = Order::where('order_id', $orderId)->first();
        if (!$order) {
            Log::warning('Binance webhook: order not found', ['order_id' => $orderId]);
            return;
        }

        if ($status === 'PAID') {
            // Payment successful
            $order->update(['status' => 'payment_confirmed']);
            
            // Create or update transaction
            $order->transactions()->updateOrCreate(
                ['payment_method' => 'binance'],
                [
                    'transaction_id' => $event['prepayId'] ?? null,
                    'amount' => $order->total,
                    'status' => 'completed',
                    'metadata' => [
                        'binance_prepay_id' => $event['prepayId'] ?? null,
                        'binance_status' => $status,
                    ],
                ]
            );

            // Dispatch delivery
            if (config('services.feature.async_delivery')) {
                dispatch(new \App\Jobs\DeliverTopUp($order->id));
            } else {
                $topUpService = app(TopUpService::class);
                $topUpService->deliverTopUp($order);
            }

            Log::info('Binance payment confirmed', ['order_id' => $orderId]);

        } elseif ($status === 'CANCEL' || $status === 'CLOSED') {
            // Payment cancelled
            $order->update(['status' => 'failed']);
            Log::info('Binance payment cancelled', ['order_id' => $orderId]);
        }
    }

    /**
     * Handle Stripe Connect transfer.created event
     */
    private function handleTransferCreated($event): void
    {
        $stripeService = app(StripeConnectService::class);
        $stripeService->handleTransferCreated($event->data->object);
    }

    /**
     * Handle Stripe Connect transfer.failed event
     */
    private function handleTransferFailed($event): void
    {
        $stripeService = app(StripeConnectService::class);
        $stripeService->handleTransferFailed($event->data->object);
    }

    /**
     * Handle Stripe Connect transfer.reversed event (refund)
     */
    private function handleTransferReversed($event): void
    {
        $transfer = $event->data->object;
        
        Log::info('Transfer reversed', [
            'transfer_id' => $transfer->id,
            'amount' => $transfer->amount,
        ]);

        // Find payout by stripe_transfer_id and mark as reversed
        $payout = \App\Models\Payout::where('stripe_transfer_id', $transfer->id)->first();
        
        if ($payout) {
            $payout->update([
                'status' => 'reversed',
                'reversed_at' => now(),
            ]);

            // Notify seller of reversal
            Log::info('Payout reversed', ['payout_id' => $payout->id]);
        }
    }
}
