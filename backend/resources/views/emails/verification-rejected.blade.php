@component('mail::message')
# Identity Verification - Needs Attention ❌

Hi {{ $verification->user->name }},

Unfortunately, your identity verification was not approved. Here's why:

**Reason:** {{ $verification->rejection_reason }}

## What can you do?

1. Review the rejection reason carefully
2. Ensure your documents are:
   - Clear and legible
   - Showing your full face and identifying features
   - Currently valid (not expired)
3. Submit a new verification with corrected documents

## Resubmit Your Verification
Visit your dashboard and upload new documents for verification.

[Go to Dashboard]({{ config('app.url') }}/dashboard)

If you believe this is an error or need support, please contact our support team.

@component('mail::footer')
© {{ date('Y') }} Dave TopUp. All rights reserved.
@endcomponent
@endcomponent
