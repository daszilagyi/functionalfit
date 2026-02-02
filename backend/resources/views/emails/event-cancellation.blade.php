<x-mail::message>
# Session Cancelled

Hello {{ $client->user->name }},

Your personal training session **{{ $event->title }}** scheduled for {{ $event->starts_at->format('l, F j, Y') }} at {{ $event->starts_at->format('H:i') }} has been cancelled.

<x-mail::button :url="config('app.frontend_url') . '/events'">
View Your Schedule
</x-mail::button>

If you have any questions or would like to reschedule, please contact us.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
