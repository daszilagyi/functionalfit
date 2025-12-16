<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\ClassRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClassReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ClassRegistration $registration
    ) {}

    public function envelope(): Envelope
    {
        $classTemplate = $this->registration->occurrence->template;

        return new Envelope(
            subject: "Reminder: {$classTemplate->title} Tomorrow",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.class-reminder',
            with: [
                'registration' => $this->registration,
                'occurrence' => $this->registration->occurrence,
                'classTemplate' => $this->registration->occurrence->template,
                'client' => $this->registration->client,
                'room' => $this->registration->occurrence->room,
                'trainer' => $this->registration->occurrence->trainer,
            ],
        );
    }
}
