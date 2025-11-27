<?php
// app/Http/Controllers/PaymentController.php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Transaction;
use App\Services\PaymentService;
use App\Services\VoucherService;
use App\Services\TopUpService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    protected $paymentService;
    protected $voucherService;
    protected $topUpService;

    public function __construct(
        PaymentService $paymentService,
        VoucherService $voucherService,
        TopUpService $topUpService
    ) {
        $this->paymentService = $paymentService;
        $this->voucherService = $voucherService;
        $this->topUpService = $topUpService;
    }

    /**
     * Process card payment via Stripe
     */
    public function processCard(Request $request)
    {
        $validated = $request->validate([
            'orderId' => 'required|string',
            'stripeToken' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|size:3',
        ]);

        try {
            $order = Order::where('order_id', $validated['orderId'])->first();

            if (!$order) {
                return response()->json(['success' => false, 'message' => 'Order not found'], 404);
            }

            // Check amount matches
            if ((float) $validated['amount'] !== (float) $order->total) {
                return response()->json(['success' => false, 'message' => 'Amount mismatch'], 400);
            }

            // Check for duplicate (idempotency)
            $existingTransaction = Transaction::where('order_id', $order->id)
                ->where('payment_method', 'card')
                ->where('status', 'completed')
                ->first();

            if ($existingTransaction) {
                return response()->json([
                    'success' => true,
                    'status' => 'succeeded',
                    'transactionId' => $existingTransaction->transaction_id,
                ]);
            }

            // Process payment
            $result = $this->paymentService->chargeCard(
                $validated['stripeToken'],
                (int) ($validated['amount'] * 100), // Convert to cents
                $validated['currency'],
                [
                    'orderId' => $order->order_id,
                    'email' => $order->email,
                    'description' => "Game top-up for {$order->player_nickname}",
                ]
            );

            if ($result['status'] === 'succeeded') {
                // Create transaction record
                $transaction = Transaction::create([
                    'order_id' => $order->id,
                    'transaction_id' => $result['transactionId'],
                    'payment_method' => 'card',
                    'amount' => $validated['amount'],
                    'currency' => strtoupper($validated['currency']),
                    'status' => 'completed',
                    'metadata' => json_encode($result['metadata'] ?? []),
                ]);

                // Update order status
                $order->update(['status' => 'payment_confirmed']);

                // Queue top-up delivery
                dispatch(new \App\Jobs\DeliverTopUp($order->id));

                return response()->json([
                    'success' => true,
                    'status' => 'succeeded',
                    'transactionId' => $transaction->transaction_id,
                ]);
            } elseif ($result['status'] === 'requires_action') {
                // 3D Secure required
                Transaction::create([
                    'order_id' => $order->id,
                    'transaction_id' => $result['transactionId'],
                    'payment_method' => 'card',
                    'amount' => $validated['amount'],
                    'currency' => strtoupper($validated['currency']),
                    'status' => 'requires_3d_secure',
                    'metadata' => json_encode($result),
                ]);

                return response()->json([
                    'success' => false,
                    'status' => 'requires_action',
                    'clientSecret' => $result['clientSecret'],
                    'message' => '3D Secure verification required',
                ], 402);
            } else {
                // Payment declined
                Transaction::create([
                    'order_id' => $order->id,
                    'transaction_id' => $result['transactionId'] ?? Str::uuid(),
                    'payment_method' => 'card',
                    'amount' => $validated['amount'],
                    'currency' => strtoupper($validated['currency']),
                    'status' => 'failed',
                    'metadata' => json_encode(['error' => $result['error'] ?? 'Unknown error']),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $result['error'] ?? 'Card declined',
                ], 402);
            }
        } catch (\Exception $e) {
            \Log::error('Card payment error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Payment processing failed',
            ], 500);
        }
    }

    /**
     * Initiate PayPal payment
     */
    public function initiatePayPal(Request $request)
    {
        $validated = $request->validate([
            'orderId' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
        ]);

        try {
            $order = Order::where('order_id', $validated['orderId'])->first();

            if (!$order) {
                return response()->json(['success' => false, 'message' => 'Order not found'], 404);
            }

            // Create PayPal order
            $result = $this->paymentService->createPayPalOrder(
                $validated['amount'],
                "Game top-up: {$order->player_nickname}",
                route('api.payments.paypal.capture', ['orderId' => $order->order_id])
            );

            if (!$result['success']) {
                return response()->json(['success' => false, 'message' => $result['error']], 500);
            }

            // Store pending transaction
            Transaction::create([
                'order_id' => $order->id,
                'transaction_id' => $result['paypalOrderId'],
                'payment_method' => 'paypal',
                'amount' => $validated['amount'],
                'currency' => 'USD',
                'status' => 'pending',
                'metadata' => json_encode(['paypalOrderId' => $result['paypalOrderId']]),
            ]);

            return response()->json([
                'success' => true,
                'approvalUrl' => $result['approvalUrl'],
            ]);
        } catch (\Exception $e) {
            \Log::error('PayPal initiation error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'PayPal initiation failed',
            ], 500);
        }
    }

    /**
     * Capture PayPal payment
     */
    public function capturePayPal(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'orderId' => 'required|string',
        ]);

        try {
            $order = Order::where('order_id', $validated['orderId'])->first();

            if (!$order) {
                return response()->json(['success' => false, 'message' => 'Order not found'], 404);
            }

            // Capture PayPal payment
            $result = $this->paymentService->capturePayPalOrder($validated['token']);

            if ($result['success']) {
                // Update transaction
                $transaction = Transaction::where('order_id', $order->id)
                    ->where('payment_method', 'paypal')
                    ->latest()
                    ->first();

                if ($transaction) {
                    $transaction->update([
                        'status' => 'completed',
                        'transaction_id' => $result['transactionId'],
                    ]);
                }

                // Update order
                $order->update(['status' => 'payment_confirmed']);

                // Queue top-up
                dispatch(new \App\Jobs\DeliverTopUp($order->id));

                return response()->json(['success' => true, 'orderId' => $order->order_id]);
            } else {
                return response()->json(['success' => false, 'message' => $result['error']], 402);
            }
        } catch (\Exception $e) {
            \Log::error('PayPal capture error', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Payment capture failed'], 500);
        }
    }

    /**
     * Initiate Binance Pay
     */
    public function initiateBinance(Request $request)
    {
        $validated = $request->validate([
            'orderId' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
        ]);

        try {
            $order = Order::where('order_id', $validated['orderId'])->first();

            if (!$order) {
                return response()->json(['success' => false, 'message' => 'Order not found'], 404);
            }

            // Create Binance Pay order
            $result = $this->paymentService->createBinancePayOrder(
                $validated['amount'],
                "Game top-up",
                route('api.payments.binance.callback', ['orderId' => $order->order_id])
            );

            if (!$result['success']) {
                return response()->json(['success' => false, 'message' => $result['error']], 500);
            }

            // Store pending transaction
            Transaction::create([
                'order_id' => $order->id,
                'transaction_id' => $result['prepayId'],
                'payment_method' => 'binance',
                'amount' => $validated['amount'],
                'currency' => 'USDT',
                'status' => 'pending',
                'metadata' => json_encode($result['metadata'] ?? []),
            ]);

            return response()->json([
                'success' => true,
                'checkoutUrl' => $result['checkoutUrl'],
            ]);
        } catch (\Exception $e) {
            \Log::error('Binance Pay error', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Binance Pay failed'], 500);
        }
    }

    /**
     * Redeem voucher
     */
    public function redeemVoucher(Request $request)
    {
        $validated = $request->validate([
            'orderId' => 'required|string',
            'voucherCode' => 'required|string|min:6|max:50',
        ]);

        try {
            $order = Order::where('order_id', $validated['orderId'])->first();

            if (!$order) {
                return response()->json(['success' => false, 'message' => 'Order not found'], 404);
            }

            // Validate voucher
            $voucherResult = $this->voucherService->validateAndRedeemVoucher(
                $validated['voucherCode'],
                $order->total
            );

            if (!$voucherResult['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => $voucherResult['reason'],
                ], 400);
            }

            // Create transaction
            Transaction::create([
                'order_id' => $order->id,
                'transaction_id' => $voucherResult['transactionId'],
                'payment_method' => 'voucher',
                'amount' => $order->total,
                'currency' => 'USD',
                'status' => $voucherResult['status'], // 'completed' or 'pending'
                'metadata' => json_encode([
                    'voucherCode' => $validated['voucherCode'],
                    'manualVerificationRequired' => $voucherResult['manualVerification'],
                ]),
            ]);

            // Update order
            $order->update([
                'status' => $voucherResult['status'] === 'completed'
                    ? 'payment_confirmed'
                    : 'payment_pending_verification',
            ]);

            // Queue top-up if auto-approved
            if ($voucherResult['status'] === 'completed') {
                dispatch(new \App\Jobs\DeliverTopUp($order->id));
            }

            return response()->json([
                'success' => true,
                'status' => $voucherResult['status'],
                'message' => $voucherResult['status'] === 'completed'
                    ? 'Voucher redeemed successfully'
                    : 'Voucher submitted for manual verification',
            ]);
        } catch (\Exception $e) {
            \Log::error('Voucher redemption error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Voucher processing failed',
            ], 500);
        }
    }
}
