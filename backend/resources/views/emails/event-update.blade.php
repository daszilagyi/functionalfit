<x-mail::message>
# Session Updated

Hello {{ $client->user->name }},

Your personal training session has been updated.

<x-mail::panel>
**Updated Session Details:**

- **Title:** {{ $event->title }}
- **Date:** {{ $event->starts_at->format('l, F j, Y') }}
- **Time:** {{ $event->starts_at->format('H:i') }} - {{ $event->ends_at->format('H:i') }}
- **Location:** {{ $room->name }}
- **Trainer:** {{ $staff->user->name }}
</x-mail::panel>

<x-mail::button :url="config('app.frontend_url') . '/events/' . $event->id">
View Session Details
</x-mail::button>

If you have any questions or concerns, please contact us.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
