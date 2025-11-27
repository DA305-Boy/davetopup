@component('mail::message')
# Payout Completed! 💰

Hi {{ $payout->store->user->name }},

Your payout has been successfully processed!

## Payout Details

| Detail | Value |
|--------|-------|
| Amount | {{ $payout->currency === 'USD' ? '$' : '' }}{{ number_format($payout->amount, 2) }} {{ $payout->currency }} |
| Transfer ID | `{{ substr($payout->stripe_transfer_id, 0, 20) }}...` |
| Store | {{ $payout->store->store_name }} |
| Completed At | {{ $payout->created_at->format('M d, Y H:i A') }} |

The funds should arrive in your connected bank account within 1-2 business days.

## View in Dashboard
[Go to Your Payouts]({{ config('app.url') }}/dashboard/payouts)

Thank you for selling on Dave TopUp!

@component('mail::footer')
© {{ date('Y') }} Dave TopUp. All rights reserved.
@endcomponent
@endcomponent
