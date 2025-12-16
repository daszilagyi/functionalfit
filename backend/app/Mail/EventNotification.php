<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EventNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Event $event,
        public string $notificationType = 'event_confirmation'
    ) {}

    public function envelope(): Envelope
    {
        $subject = match ($this->notificationType) {
            'event_confirmation' => "Event Confirmed: {$this->event->title}",
            'event_update' => "Event Updated: {$this->event->title}",
            'event_cancellation' => "Event Cancelled: {$this->event->title}",
            'event_reminder' => "Reminder: {$this->event->title}",
            default => "Event Notification: {$this->event->title}",
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $template = match ($this->notificationType) {
            'event_confirmation' => 'emails.event-confirmation',
            'event_update' => 'emails.event-update',
            'event_cancellation' => 'emails.event-cancellation',
            'event_reminder' => 'emails.event-reminder',
            default => 'emails.event-notification',
        };

        return new Content(
            markdown: $template,
            with: [
                'event' => $this->event,
                'client' => $this->event->client,
                'staff' => $this->event->staff,
                'room' => $this->event->room,
                'notificationType' => $this->notificationType,
            ],
        );
    }
}
