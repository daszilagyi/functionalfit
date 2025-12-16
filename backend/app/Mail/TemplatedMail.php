<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TemplatedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * Create a new message instance.
     *
     * @param string $emailSubject Email subject line
     * @param string $htmlContent Rendered HTML content
     * @param string|null $textContent Rendered plain text content (fallback)
     */
    public function __construct(
        public string $emailSubject,
        public string $htmlContent,
        public ?string $textContent = null
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->emailSubject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.templated',
            text: $this->textContent !== null ? 'emails.templated-text' : null,
            with: [
                'htmlContent' => $this->htmlContent,
                'textContent' => $this->textContent,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
