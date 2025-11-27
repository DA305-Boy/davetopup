<?php

namespace App\Services;

use App\Models\Voucher;
use App\Models\Order;
use Exception;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;

class VoucherService
{
    /**
     * Validate and redeem a voucher code
     *
     * @param string $code Voucher code
     * @param float $amount Required amount to cover
     * @param Order $order Order instance
     * @return array Validation result with status and message
     */
    public function redeemVoucher(string $code, float $amount, Order $order): array
    {
        try {
            // Normalize code
            $code = strtoupper(trim($code));

            // Log redemption attempt
            Log::info('Voucher redemption attempt', [
                'code' => substr($code, 0, 10) . '***', // Mask sensitive part
                'amount' => $amount,
                'order_id' => $order->id,
            ]);

            // Check if voucher exists and is valid
            $voucher = $this->validateVoucherCode($code);

            if (!$voucher['valid']) {
                return [
                    'success' => false,
                    'status' => 'invalid',
                    'message' => $voucher['message'],
                ];
            }

            // Check balance
            if ($voucher['balance'] < $amount) {
                return [
                    'success' => false,
                    'status' => 'insufficient_balance',
                    'message' => "Voucher balance ({$voucher['balance']}) is less than required amount ({$amount})",
                    'balance' => $voucher['balance'],
                ];
            }

            // For high-value or suspicious vouchers, require manual verification
            $requiresVerification = $this->requiresManualVerification($voucher, $amount);

            if ($requiresVerification) {
                return [
                    'success' => true,
                    'status' => 'pending_verification',
                    'message' => 'Voucher is pending manual verification by admin',
                    'balance' => $voucher['balance'],
                ];
            }

            // Auto-approve and mark as redeemed
            $redeemResult = $this->markVoucherRedeemed($code, $amount);

            if (!$redeemResult['success']) {
                return [
                    'success' => false,
                    'status' => 'redemption_failed',
                    'message' => $redeemResult['message'],
                ];
            }

            Log::info('Voucher successfully redeemed', [
                'code' => substr($code, 0, 10) . '***',
                'amount' => $amount,
                'order_id' => $order->id,
            ]);

            return [
                'success' => true,
                'status' => 'completed',
                'message' => 'Voucher successfully applied',
                'remaining_balance' => $redeemResult['remaining_balance'],
                'applied_amount' => $amount,
            ];

        } catch (Exception $e) {
            Log::error('Voucher redemption error', [
                'code' => $code ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status' => 'error',
                'message' => 'An error occurred while redeeming the voucher',
            ];
        }
    }

    /**
     * Validate voucher code format and existence
     *
     * @param string $code Voucher code
     * @return array Validation result
     */
    private function validateVoucherCode(string $code): array
    {
        // First check local database
        $voucher = Voucher::where('code', $code)
            ->where('is_active', true)
            ->first();

        if (!$voucher) {
            // Try external voucher provider API if configured
            $external = $this->validateExternalVoucher($code);
            if ($external['valid']) {
                return $external;
            }

            return [
                'valid' => false,
                'message' => 'Voucher code not found or expired',
            ];
        }

        // Check expiration
        if ($voucher->expires_at && $voucher->expires_at->isPast()) {
            return [
                'valid' => false,
                'message' => 'Voucher has expired',
            ];
        }

        // Check max uses
        if ($voucher->max_uses && $voucher->used_count >= $voucher->max_uses) {
            return [
                'valid' => false,
                'message' => 'Voucher usage limit reached',
            ];
        }

        return [
            'valid' => true,
            'balance' => $voucher->amount,
            'source' => 'local',
            'voucher_id' => $voucher->id,
            'expires_at' => $voucher->expires_at?->toIso8601String(),
        ];
    }

    /**
     * Validate voucher against external provider
     *
     * @param string $code Voucher code
     * @return array External validation result
     */
    private function validateExternalVoucher(string $code): array
    {
        $apiKey = config('services.voucher_provider.api_key');
        $apiUrl = config('services.voucher_provider.url');

        if (!$apiKey || !$apiUrl) {
            return ['valid' => false];
        }

        try {
            $client = new Client();
            $response = $client->get("{$apiUrl}/validate", [
                'headers' => [
                    'Authorization' => "Bearer {$apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'query' => ['code' => $code],
                'timeout' => 5,
            ]);

            $data = json_decode($response->getBody(), true);

            if ($data['success'] ?? false) {
                return [
                    'valid' => true,
                    'balance' => $data['balance'] ?? 0,
                    'source' => 'external',
                    'provider' => $data['provider'] ?? 'unknown',
                ];
            }

            return ['valid' => false];

        } catch (Exception $e) {
            Log::warning('External voucher validation failed', [
                'error' => $e->getMessage(),
                'code' => substr($code, 0, 10) . '***',
            ]);

            return ['valid' => false];
        }
    }

    /**
     * Check if voucher requires manual verification
     *
     * @param array $voucher Voucher data
     * @param float $amount Transaction amount
     * @return bool Requires verification
     */
    private function requiresManualVerification(array $voucher, float $amount): bool
    {
        // High-value transactions
        if ($amount > 100) {
            return true;
        }

        // External vouchers require verification
        if (($voucher['source'] ?? '') === 'external') {
            return true;
        }

        // Vouchers close to expiration
        if ($voucher['expires_at'] ?? null) {
            $expiresAt = now()->parse($voucher['expires_at']);
            if ($expiresAt->diffInDays() < 7) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mark voucher as redeemed
     *
     * @param string $code Voucher code
     * @param float $amount Amount being redeemed
     * @return array Redemption result
     */
    private function markVoucherRedeemed(string $code, float $amount): array
    {
        try {
            $voucher = Voucher::where('code', $code)
                ->lockForUpdate()
                ->first();

            if (!$voucher) {
                return [
                    'success' => false,
                    'message' => 'Voucher not found',
                ];
            }

            // Update usage count
            $voucher->used_count++;
            $voucher->save();

            // If this is a partially redeemable voucher, create a new one with remaining balance
            if ($voucher->amount > $amount && $voucher->is_reusable) {
                $remaining = $voucher->amount - $amount;
                Voucher::create([
                    'code' => $this->generateVoucherCode(),
                    'amount' => $remaining,
                    'max_uses' => 1,
                    'is_active' => true,
                    'expires_at' => $voucher->expires_at,
                ]);
            }

            return [
                'success' => true,
                'remaining_balance' => max(0, $voucher->amount - $amount),
            ];

        } catch (Exception $e) {
            Log::error('Failed to mark voucher as redeemed', [
                'code' => substr($code, 0, 10) . '***',
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to process voucher redemption',
            ];
        }
    }

    /**
     * Manually verify and approve a pending voucher
     *
     * @param string $code Voucher code
     * @param bool $approve Approve or reject
     * @param string $notes Admin notes
     * @return array Result
     */
    public function manualVerifyVoucher(string $code, bool $approve, string $notes = ''): array
    {
        try {
            $code = strtoupper(trim($code));

            Log::info('Manual voucher verification', [
                'code' => substr($code, 0, 10) . '***',
                'approve' => $approve,
                'notes' => $notes,
            ]);

            if ($approve) {
                return [
                    'success' => true,
                    'message' => 'Voucher approved for delivery',
                ];
            } else {
                // Mark transaction as failed
                return [
                    'success' => true,
                    'message' => 'Voucher rejected',
                ];
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate a unique voucher code
     *
     * @return string Voucher code
     */
    private function generateVoucherCode(): string
    {
        do {
            $code = 'GIFT-' . strtoupper(bin2hex(random_bytes(5))) . '-' . now()->format('YmdHi');
        } while (Voucher::where('code', $code)->exists());

        return $code;
    }

    /**
     * Create a new voucher (admin only)
     *
     * @param float $amount Voucher amount
     * @param int|null $maxUses Maximum uses
     * @param string|null $expiresAt Expiration date
     * @return array Created voucher info
     */
    public function createVoucher(float $amount, ?int $maxUses = 1, ?string $expiresAt = null): array
    {
        try {
            $code = $this->generateVoucherCode();

            $voucher = Voucher::create([
                'code' => $code,
                'amount' => $amount,
                'max_uses' => $maxUses,
                'expires_at' => $expiresAt ? now()->parse($expiresAt) : null,
                'is_active' => true,
            ]);

            Log::info('Voucher created', [
                'code' => $code,
                'amount' => $amount,
            ]);

            return [
                'success' => true,
                'code' => $code,
                'amount' => $amount,
                'voucher_id' => $voucher->id,
            ];

        } catch (Exception $e) {
            Log::error('Failed to create voucher', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Deactivate a voucher (admin only)
     *
     * @param string $code Voucher code
     * @return array Result
     */
    public function deactivateVoucher(string $code): array
    {
        try {
            $code = strtoupper(trim($code));

            $voucher = Voucher::where('code', $code)->first();

            if (!$voucher) {
                return [
                    'success' => false,
                    'message' => 'Voucher not found',
                ];
            }

            $voucher->update(['is_active' => false]);

            Log::info('Voucher deactivated', ['code' => substr($code, 0, 10) . '***']);

            return [
                'success' => true,
                'message' => 'Voucher deactivated',
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get voucher stats
     *
     * @param string $code Voucher code
     * @return array Stats
     */
    public function getVoucherStats(string $code): array
    {
        try {
            $code = strtoupper(trim($code));

            $voucher = Voucher::where('code', $code)->first();

            if (!$voucher) {
                return [
                    'success' => false,
                    'message' => 'Voucher not found',
                ];
            }

            return [
                'success' => true,
                'code' => $code,
                'amount' => $voucher->amount,
                'used_count' => $voucher->used_count,
                'max_uses' => $voucher->max_uses,
                'is_active' => $voucher->is_active,
                'expires_at' => $voucher->expires_at?->toIso8601String(),
                'created_at' => $voucher->created_at->toIso8601String(),
                'remaining_uses' => $voucher->max_uses ? ($voucher->max_uses - $voucher->used_count) : 'unlimited',
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
