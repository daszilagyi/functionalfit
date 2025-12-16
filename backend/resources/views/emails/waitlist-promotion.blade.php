<x-mail::message>
# Good News! You're In!

Hello {{ $client->user->name }},

Great news! A spot has opened up for **{{ $classTemplate->name }}**.

<x-mail::panel>
**Your booking is now CONFIRMED:**

- **Date:** {{ $occurrence->starts_at->format('l, F j, Y') }}
- **Time:** {{ $occurrence->starts_at->format('H:i') }} - {{ $occurrence->ends_at->format('H:i') }}
- **Location:** {{ $room->name }}
- **Trainer:** {{ $trainer->user->name }}
</x-mail::panel>

Please arrive 5-10 minutes early to check in. See you there!

<x-mail::button :url="config('app.frontend_url') . '/classes/' . $occurrence->id">
View Class Details
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
