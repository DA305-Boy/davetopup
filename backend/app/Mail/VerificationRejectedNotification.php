<?php

namespace App\Mail;

use App\Models\SellerVerification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VerificationRejectedNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $verification;

    public function __construct(SellerVerification $verification)
    {
        $this->verification = $verification;
    }

    public function build()
    {
        return $this->markdown('emails.verification-rejected', [
            'name' => $this->verification->verified_name,
            'storeName' => $this->verification->store?->name ?? 'Your Store',
            'documentType' => ucwords(str_replace('_', ' ', $this->verification->document_type)),
            'reason' => $this->verification->rejection_reason,
        ])
        ->subject('Identity Verification Needs Review');
    }
}
