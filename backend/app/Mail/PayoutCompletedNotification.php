<?php

namespace App\Mail;

use App\Models\Payout;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PayoutCompletedNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $payout;

    public function __construct(Payout $payout)
    {
        $this->payout = $payout;
    }

    public function build()
    {
        return $this->markdown('emails.payout-completed', [
            'storeName' => $this->payout->store->name,
            'amount' => number_format($this->payout->amount, 2),
            'currency' => $this->payout->currency,
            'transferId' => $this->payout->stripe_transfer_id,
        ])
        ->subject('Payout Completed: $' . number_format($this->payout->amount, 2));
    }
}
