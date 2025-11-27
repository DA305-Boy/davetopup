<?php

namespace App\Services;

use Exception;
use Stripe\Exception\CardException;
use Stripe\Exception\RateLimitException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Exception\AuthenticationException;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\ApiErrorException;
use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Orders\OrdersCreateRequest;
use PayPalCheckoutSdk\Orders\OrdersCaptureRequest;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    /**
     * Process Stripe card payment
     *
     * @param string $token Stripe token from frontend
     * @param float $amount Payment amount in dollars
     * @param string $currency Currency code (usd, eur, etc)
     * @param array $metadata Additional transaction metadata
     * @return array Payment result
     * @throws Exception
     */
    public function processStripePayment(string $token, float $amount, string $currency = 'usd', array $metadata = []): array
    {
        try {
            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

            // Create payment intent for 3D Secure support
            $intent = \Stripe\PaymentIntent::create([
                'amount' => (int)($amount * 100), // Convert to cents
                'currency' => strtolower($currency),
                'payment_method_data' => [
                    'type' => 'card',
                    'card' => [
                        'token' => $token,
                    ],
                ],
                'confirm' => true,
                'return_url' => config('app.url') . '/checkout/success',
                'metadata' => array_merge($metadata, [
                    'source' => 'davetopup_checkout',
                    'timestamp' => now()->toIso8601String(),
                ]),
                // Enable 3D Secure
                'statement_descriptor' => 'DaveTopUp Game',
            ]);

            Log::info('Stripe payment intent created', [
                'intent_id' => $intent->id,
                'status' => $intent->status,
                'amount' => $amount,
            ]);

            // Check if 3D Secure is required
            if ($intent->status === 'requires_action') {
                return [
                    'success' => false,
                    'status' => 'requires_3d_secure',
                    'client_secret' => $intent->client_secret,
                    'message' => '3D Secure authentication required',
                ];
            }

            // Payment succeeded
            if ($intent->status === 'succeeded') {
                return [
                    'success' => true,
                    'status' => 'completed',
                    'transaction_id' => $intent->id,
                    'charge_id' => $intent->charges->data[0]->id ?? null,
                    'amount' => $amount,
                    'currency' => $currency,
                ];
            }

            // Payment failed
            throw new Exception('Payment intent failed: ' . $intent->status);

        } catch (CardException $e) {
            Log::warning('Stripe card error', [
                'error' => $e->getError()->message,
                'code' => $e->getError()->code,
            ]);

            return [
                'success' => false,
                'status' => 'failed',
                'error' => $e->getError()->message,
                'code' => $e->getError()->code,
            ];

        } catch (RateLimitException $e) {
            Log::error('Stripe rate limit', ['error' => $e->getMessage()]);
            throw new Exception('Payment processing temporarily unavailable. Please try again.');

        } catch (InvalidRequestException $e) {
            Log::error('Stripe invalid request', ['error' => $e->getMessage()]);
            throw new Exception('Invalid payment details provided.');

        } catch (AuthenticationException $e) {
            Log::error('Stripe authentication failed', ['error' => $e->getMessage()]);
            throw new Exception('Payment service authentication error.');

        } catch (ApiConnectionException $e) {
            Log::error('Stripe connection error', ['error' => $e->getMessage()]);
            throw new Exception('Payment service connection error.');

        } catch (ApiErrorException $e) {
            Log::error('Stripe API error', ['error' => $e->getMessage()]);
            throw new Exception('Payment processing failed.');
        }
    }

    /**
     * Confirm 3D Secure payment after user authentication
     *
     * @param string $clientSecret PaymentIntent client secret
     * @return array Payment result
     * @throws Exception
     */
    public function confirm3DSecurePayment(string $clientSecret): array
    {
        try {
            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

            // Retrieve the payment intent
            $parts = explode('_secret_', $clientSecret);
            $intentId = $parts[0] ?? null;

            if (!$intentId) {
                throw new Exception('Invalid client secret');
            }

            $intent = \Stripe\PaymentIntent::retrieve($intentId);

            if ($intent->status === 'succeeded') {
                return [
                    'success' => true,
                    'status' => 'completed',
                    'transaction_id' => $intent->id,
                ];
            }

            return [
                'success' => false,
                'status' => $intent->status,
                'error' => 'Payment authentication failed',
            ];

        } catch (Exception $e) {
            Log::error('3D Secure confirmation failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Initiate PayPal payment
     *
     * @param string $orderId Order ID
     * @param float $amount Payment amount
     * @param string $currency Currency code
     * @param string $returnUrl Return URL after PayPal approval
     * @return array PayPal approval URL
     * @throws Exception
     */
    public function initiatePayPalPayment(
        string $orderId,
        float $amount,
        string $currency = 'USD',
        string $returnUrl = ''
    ): array {
        try {
            $environment = config('services.paypal.mode') === 'sandbox'
                ? new \PayPalCheckoutSdk\Core\SandboxEnvironment(
                    config('services.paypal.client_id'),
                    config('services.paypal.secret')
                )
                : new \PayPalCheckoutSdk\Core\ProductionEnvironment(
                    config('services.paypal.client_id'),
                    config('services.paypal.secret')
                );

            $client = new PayPalHttpClient($environment);
            $request = new OrdersCreateRequest();
            $request->prefer('return=representation');

            $returnUrl = $returnUrl ?: config('app.url') . '/checkout/paypal/return';
            $cancelUrl = config('app.url') . '/checkout/cancel';

            $request->body = [
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'reference_id' => $orderId,
                        'amount' => [
                            'currency_code' => strtoupper($currency),
                            'value' => number_format($amount, 2, '.', ''),
                            'breakdown' => [
                                'item_total' => [
                                    'currency_code' => strtoupper($currency),
                                    'value' => number_format($amount, 2, '.', ''),
                                ],
                            ],
                        ],
                        'items' => [
                            [
                                'name' => 'Game Currency Bundle',
                                'unit_amount' => [
                                    'currency_code' => strtoupper($currency),
                                    'value' => number_format($amount, 2, '.', ''),
                                ],
                                'quantity' => '1',
                                'category' => 'DIGITAL_GOODS',
                            ],
                        ],
                        'shipping' => [
                            'name' => [
                                'full_name' => 'Digital Delivery',
                            ],
                            'type' => 'PICKUP',
                        ],
                    ],
                ],
                'application_context' => [
                    'return_url' => $returnUrl,
                    'cancel_url' => $cancelUrl,
                    'brand_name' => 'DaveTopUp',
                    'user_action' => 'PAY_NOW',
                    'locale' => 'en-US',
                ],
            ];

            $response = $client->execute($request);

            Log::info('PayPal order created', [
                'order_id' => $response->result->id,
                'status' => $response->result->status,
                'reference_id' => $orderId,
            ]);

            // Find approval link
            $approvalUrl = null;
            foreach ($response->result->links as $link) {
                if ($link->rel === 'approve') {
                    $approvalUrl = $link->href;
                    break;
                }
            }

            return [
                'success' => true,
                'paypal_order_id' => $response->result->id,
                'approval_url' => $approvalUrl,
                'status' => $response->result->status,
            ];

        } catch (\Exception $e) {
            Log::error('PayPal initiation failed', [
                'error' => $e->getMessage(),
                'order_id' => $orderId,
            ]);

            throw new Exception('PayPal payment initiation failed: ' . $e->getMessage());
        }
    }

    /**
     * Capture PayPal payment after user approval
     *
     * @param string $paypalOrderId PayPal Order ID
     * @param string $orderId Dave TopUp Order ID
     * @return array Payment result
     * @throws Exception
     */
    public function capturePayPalPayment(string $paypalOrderId, string $orderId = ''): array
    {
        try {
            $environment = config('services.paypal.mode') === 'sandbox'
                ? new \PayPalCheckoutSdk\Core\SandboxEnvironment(
                    config('services.paypal.client_id'),
                    config('services.paypal.secret')
                )
                : new \PayPalCheckoutSdk\Core\ProductionEnvironment(
                    config('services.paypal.client_id'),
                    config('services.paypal.secret')
                );

            $client = new PayPalHttpClient($environment);
            $request = new OrdersCaptureRequest($paypalOrderId);
            $request->prefer('return=representation');

            $response = $client->execute($request);

            Log::info('PayPal payment captured', [
                'paypal_order_id' => $paypalOrderId,
                'status' => $response->result->status,
                'order_id' => $orderId,
            ]);

            // Extract transaction ID from response
            $transactionId = null;
            if (!empty($response->result->purchase_units[0]->payments->captures)) {
                $transactionId = $response->result->purchase_units[0]->payments->captures[0]->id;
            }

            return [
                'success' => true,
                'status' => 'completed',
                'transaction_id' => $transactionId ?? $paypalOrderId,
                'paypal_order_id' => $paypalOrderId,
                'payer_email' => $response->result->payer->email_address ?? null,
            ];

        } catch (\Exception $e) {
            Log::error('PayPal capture failed', [
                'error' => $e->getMessage(),
                'paypal_order_id' => $paypalOrderId,
            ]);

            throw new Exception('PayPal payment capture failed: ' . $e->getMessage());
        }
    }

    /**
     * Process Binance Pay payment
     *
     * @param string $orderId Order ID
     * @param float $amount Amount in USD
     * @param string $returnUrl Return URL after Binance payment
     * @return array Binance checkout URL
     * @throws Exception
     */
    public function initiateBinancePayment(
        string $orderId,
        float $amount,
        string $returnUrl = ''
    ): array {
        try {
            $client = new Client();
            $timestamp = (int)(microtime(true) * 1000);
            $nonce = bin2hex(random_bytes(16));

            $payload = json_encode([
                'merchantId' => config('services.binance.merchant_id'),
                'merchantTradeNo' => $orderId,
                'totalFeeInUsd' => number_format($amount, 2, '.', ''),
                'currency' => 'USDT',
                'goods' => [
                    'goodsType' => '0',
                    'goodsCategory' => 'D001',
                    'referenceGoodsId' => $orderId,
                    'goodsName' => 'Game Currency',
                    'goodsDetail' => 'Dave TopUp game currency',
                ],
                'buyer' => [
                    'buyerEmail' => 'customer@davetopup.local',
                ],
                'returnUrl' => $returnUrl ?: config('app.url') . '/checkout/success',
                'cancelUrl' => config('app.url') . '/checkout/cancel',
                'webhookUrl' => config('app.url') . '/webhooks/binance',
                'requestTime' => $timestamp,
                'expireTime' => $timestamp + 900000, // 15 minutes
            ]);

            $signature = $this->generateBinanceSignature($payload);

            $response = $client->post('https://api.binance.com/openapi/walletcore/unified-checkout/order', [
                'json' => json_decode($payload, true),
                'headers' => [
                    'Content-Type' => 'application/json',
                    'BinancePay-Timestamp' => $timestamp,
                    'BinancePay-Nonce' => $nonce,
                    'BinancePay-Certificate-SN' => config('services.binance.certificate_sn'),
                    'BinancePay-Signature' => $signature,
                ],
            ]);

            $result = json_decode($response->getBody(), true);

            if ($result['status'] !== 'SUCCESS') {
                throw new Exception('Binance API error: ' . ($result['errorMessage'] ?? 'Unknown error'));
            }

            Log::info('Binance payment initiated', [
                'order_id' => $orderId,
                'checkout_url' => $result['data']['checkoutUrl'] ?? null,
            ]);

            return [
                'success' => true,
                'checkout_url' => $result['data']['checkoutUrl'],
                'binance_order_id' => $result['data']['prepayId'],
                'expires_at' => now()->addMinutes(15),
            ];

        } catch (\Exception $e) {
            Log::error('Binance payment initiation failed', [
                'error' => $e->getMessage(),
                'order_id' => $orderId,
            ]);

            throw new Exception('Binance payment initiation failed: ' . $e->getMessage());
        }
    }

    /**
     * Verify Binance webhook signature
     *
     * @param string $payload Request body
     * @param string $signature Signature header
     * @return bool Signature is valid
     */
    public function verifyBinanceSignature(string $payload, string $signature): bool
    {
        $expected = $this->generateBinanceSignature($payload);
        return hash_equals($expected, $signature);
    }

    /**
     * Generate Binance signature
     *
     * @param string $payload Request payload
     * @return string HMAC signature
     */
    private function generateBinanceSignature(string $payload): string
    {
        $secret = config('services.binance.secret_key');
        return base64_encode(
            hash_hmac('sha256', $payload, $secret, true)
        );
    }

    /**
     * Refund a payment (Stripe or PayPal)
     *
     * @param string $transactionId Transaction ID
     * @param string $paymentMethod Payment method
     * @param float $amount Amount to refund (optional, full refund if null)
     * @return array Refund result
     * @throws Exception
     */
    public function refundPayment(string $transactionId, string $paymentMethod, ?float $amount = null): array
    {
        try {
            if ($paymentMethod === 'card') {
                return $this->refundStripePayment($transactionId, $amount);
            } elseif ($paymentMethod === 'paypal') {
                return $this->refundPayPalPayment($transactionId, $amount);
            } else {
                throw new Exception("Refunds not supported for payment method: {$paymentMethod}");
            }

        } catch (Exception $e) {
            Log::error('Refund failed', [
                'transaction_id' => $transactionId,
                'payment_method' => $paymentMethod,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Refund Stripe payment
     *
     * @param string $chargeId Stripe charge ID
     * @param float|null $amount Refund amount
     * @return array Refund result
     * @throws Exception
     */
    private function refundStripePayment(string $chargeId, ?float $amount = null): array
    {
        try {
            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

            $params = [];
            if ($amount) {
                $params['amount'] = (int)($amount * 100);
            }

            $refund = \Stripe\Refund::create([
                'charge' => $chargeId,
                ...$params,
            ]);

            Log::info('Stripe refund processed', [
                'refund_id' => $refund->id,
                'charge_id' => $chargeId,
                'amount' => $amount,
            ]);

            return [
                'success' => true,
                'refund_id' => $refund->id,
                'status' => $refund->status,
                'amount' => $refund->amount / 100,
            ];

        } catch (ApiErrorException $e) {
            throw new Exception('Stripe refund failed: ' . $e->getMessage());
        }
    }

    /**
     * Refund PayPal payment
     *
     * @param string $captureId PayPal capture ID
     * @param float|null $amount Refund amount
     * @return array Refund result
     * @throws Exception
     */
    private function refundPayPalPayment(string $captureId, ?float $amount = null): array
    {
        try {
            $environment = config('services.paypal.mode') === 'sandbox'
                ? new \PayPalCheckoutSdk\Core\SandboxEnvironment(
                    config('services.paypal.client_id'),
                    config('services.paypal.secret')
                )
                : new \PayPalCheckoutSdk\Core\ProductionEnvironment(
                    config('services.paypal.client_id'),
                    config('services.paypal.secret')
                );

            $client = new PayPalHttpClient($environment);
            $request = new \PayPalCheckoutSdk\Payments\CapturesRefundRequest($captureId);

            if ($amount) {
                $request->body = [
                    'amount' => [
                        'currency_code' => 'USD',
                        'value' => number_format($amount, 2, '.', ''),
                    ],
                ];
            }

            $response = $client->execute($request);

            Log::info('PayPal refund processed', [
                'refund_id' => $response->result->id,
                'capture_id' => $captureId,
                'status' => $response->result->status,
            ]);

            return [
                'success' => true,
                'refund_id' => $response->result->id,
                'status' => $response->result->status,
            ];

        } catch (\Exception $e) {
            throw new Exception('PayPal refund failed: ' . $e->getMessage());
        }
    }
}
