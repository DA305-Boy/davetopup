@component('mail::message')
# Welcome! Your Identity Verification is Approved ✓

Hi {{ $verification->user->name }},

Great news! Your identity verification has been approved. You can now accept orders and start earning through your **{{ $verification->store->store_name ?? 'store' }}**.

## What's next?

- Set up your payment methods in your seller dashboard
- Configure your game top-up packages
- Start promoting your store to earn

## Quick Links
- [Dashboard]({{ config('app.url') }}/dashboard)
- [Support]({{ config('app.url') }}/support)

Thank you for joining Dave TopUp!

@component('mail::footer')
© {{ date('Y') }} Dave TopUp. All rights reserved.
@endcomponent
@endcomponent
