<x-mail::message>
# 1:1 Session Confirmed

Hello {{ $client->user->name }},

Your personal training session has been confirmed!

<x-mail::panel>
**Session Details:**

- **Title:** {{ $event->title }}
- **Date:** {{ $event->starts_at->format('l, F j, Y') }}
- **Time:** {{ $event->starts_at->format('H:i') }} - {{ $event->ends_at->format('H:i') }}
- **Location:** {{ $room->name }}
- **Trainer:** {{ $staff->user->name }}
@if($event->description)
- **Notes:** {{ $event->description }}
@endif
</x-mail::panel>

Please arrive 5-10 minutes early to check in and discuss your goals with your trainer.

<x-mail::button :url="config('app.frontend_url') . '/events/' . $event->id">
View Session Details
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
