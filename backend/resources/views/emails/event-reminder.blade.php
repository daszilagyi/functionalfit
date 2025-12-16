<x-mail::message>
# Session Reminder

Hello {{ $client->user->name }},

This is a reminder that you have a personal training session tomorrow.

<x-mail::panel>
**Session Details:**

- **Title:** {{ $event->title }}
- **Date:** {{ $event->starts_at->format('l, F j, Y') }}
- **Time:** {{ $event->starts_at->format('H:i') }} - {{ $event->ends_at->format('H:i') }}
- **Location:** {{ $room->name }}
- **Trainer:** {{ $staff->user->name }}
</x-mail::panel>

Please arrive 5-10 minutes early to warm up and prepare.

<x-mail::button :url="config('app.frontend_url') . '/events/' . $event->id">
View Session Details
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
