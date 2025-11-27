<?php

namespace App\Services;

use App\Models\Payout;
use App\Models\Store;
use Illuminate\Support\Facades\Log;

class StripeConnectService
{
    /**
     * Initiate a payout to a seller's Stripe Connect account
     */
    public function initiateTransfer(Store $store, float $amount, string $currency = 'usd'): Payout
    {
        if (!$store->stripe_account_id) {
            throw new \Exception('Store has no Stripe Connect account configured');
        }

        try {
            $transfer = \Stripe\Transfer::create([
                'amount' => (int)($amount * 100), // cents
                'currency' => strtolower($currency),
                'destination' => $store->stripe_account_id,
                'description' => "Payout for store: {$store->name}"
            ], [
                'stripe_account' => config('services.stripe.secret_key')
            ]);

            $payout = Payout::create([
                'store_id' => $store->id,
                'amount' => $amount,
                'currency' => strtoupper($currency),
                'stripe_transfer_id' => $transfer->id,
                'status' => 'processing'
            ]);

            Log::info("Transfer initiated: {$transfer->id} for store {$store->id}");
            return $payout;
        } catch (\Exception $e) {
            Log::error("Transfer failed for store {$store->id}: " . $e->getMessage());
            
            $payout = Payout::create([
                'store_id' => $store->id,
                'amount' => $amount,
                'currency' => strtoupper($currency),
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'retry_count' => 1,
                'next_retry_at' => now()->addHours(1)
            ]);

            throw $e;
        }
    }

    /**
     * Retry failed payout
     */
    public function retryPayout(Payout $payout): Payout
    {
        if ($payout->retry_count >= 5) {
            $payout->update(['status' => 'failed']);
            throw new \Exception('Max retry attempts reached');
        }

        return $this->initiateTransfer($payout->store, $payout->amount, $payout->currency);
    }

    /**
     * Handle Stripe webhook: transfer.created
     */
    public function handleTransferCreated(array $data): void
    {
        $payout = Payout::where('stripe_transfer_id', $data['id'])->first();
        if ($payout) {
            $payout->update(['status' => 'completed', 'processed_at' => now()]);
            Log::info("Transfer completed: {$data['id']}");
        }
    }

    /**
     * Handle Stripe webhook: transfer.failed
     */
    public function handleTransferFailed(array $data): void
    {
        $payout = Payout::where('stripe_transfer_id', $data['id'])->first();
        if ($payout) {
            $payout->update([
                'status' => 'failed',
                'error_message' => $data['failure_message'] ?? 'Unknown error',
                'retry_count' => $payout->retry_count + 1,
                'next_retry_at' => now()->addHours(6)
            ]);
            Log::warning("Transfer failed: {$data['id']}");
        }
    }
}
