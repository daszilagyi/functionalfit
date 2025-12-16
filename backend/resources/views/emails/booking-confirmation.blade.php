<x-mail::message>
# Booking Confirmed

Hello {{ $client->user->name }},

Your booking for **{{ $classTemplate->name }}** has been confirmed!

<x-mail::panel>
**Class Details:**

- **Date:** {{ $occurrence->starts_at->format('l, F j, Y') }}
- **Time:** {{ $occurrence->starts_at->format('H:i') }} - {{ $occurrence->ends_at->format('H:i') }}
- **Location:** {{ $room->name }}
- **Trainer:** {{ $trainer->user->name }}
@if($occurrence->credits_required)
- **Credits:** {{ $occurrence->credits_required }} credit(s) will be deducted at check-in
@endif
</x-mail::panel>

Please arrive 5-10 minutes early to check in and prepare.

@if($registration->status === 'waitlist')
**Note:** You are currently on the waitlist. We will notify you if a spot opens up.
@endif

<x-mail::button :url="config('app.frontend_url') . '/classes/' . $occurrence->id">
View Class Details
</x-mail::button>

If you need to cancel, please do so at least 24 hours in advance to avoid credit deduction.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
