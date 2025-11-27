<?php

namespace App\Mail;

use App\Models\SellerVerification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VerificationApprovedNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $verification;

    public function __construct(SellerVerification $verification)
    {
        $this->verification = $verification;
    }

    public function build()
    {
        return $this->markdown('emails.verification-approved', [
            'name' => $this->verification->verified_name,
            'storeName' => $this->verification->store?->name ?? 'Your Store',
            'documentType' => ucwords(str_replace('_', ' ', $this->verification->document_type)),
        ])
        ->subject('Your Identity Verification Has Been Approved ✓');
    }
}
