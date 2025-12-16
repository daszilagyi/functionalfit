<x-mail::message>
# Class Reminder

Hello {{ $client->user->name }},

This is a reminder that you have **{{ $classTemplate->name }}** scheduled tomorrow.

<x-mail::panel>
**Class Details:**

- **Date:** {{ $occurrence->starts_at->format('l, F j, Y') }}
- **Time:** {{ $occurrence->starts_at->format('H:i') }} - {{ $occurrence->ends_at->format('H:i') }}
- **Location:** {{ $room->name }}
- **Trainer:** {{ $trainer->user->name }}
</x-mail::panel>

**Don't forget to:**
- Bring your workout gear
- Arrive 5-10 minutes early
- Stay hydrated!

<x-mail::button :url="config('app.frontend_url') . '/classes/' . $occurrence->id">
View Class Details
</x-mail::button>

If you need to cancel, please do so as soon as possible.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
