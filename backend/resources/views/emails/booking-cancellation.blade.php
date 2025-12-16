<x-mail::message>
# Booking Cancelled

Hello {{ $client->user->name }},

Your booking for **{{ $classTemplate->name }}** on {{ $occurrence->starts_at->format('l, F j, Y') }} at {{ $occurrence->starts_at->format('H:i') }} has been cancelled.

@if($registration->credits_used > 0)
**{{ $registration->credits_used }} credit(s)** have been refunded to your account.
@endif

<x-mail::button :url="config('app.frontend_url') . '/classes'">
Browse Other Classes
</x-mail::button>

If you have any questions, please don't hesitate to contact us.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
