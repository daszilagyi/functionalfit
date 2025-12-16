<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Services\MailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendTemplatedEmail implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The backoff times in seconds (15s, 30s, 60s).
     *
     * @var array<int>
     */
    public array $backoff = [15, 30, 60];

    /**
     * Delete the job if its models no longer exist.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     *
     * @param EmailLog $emailLog
     * @param EmailTemplate $template
     * @param array<string, mixed> $variables
     * @param string|null $recipientName
     */
    public function __construct(
        public EmailLog $emailLog,
        public EmailTemplate $template,
        public array $variables,
        public ?string $recipientName = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->emailLog->incrementAttempts();

        try {
            // Render the template
            $renderedHtml = $this->template->render($this->variables);
            $renderedSubject = $this->template->renderSubject($this->variables);
            $renderedFallback = $this->template->renderFallback($this->variables);

            // Build the email
            $message = new \App\Mail\TemplatedMail(
                emailSubject: $renderedSubject,
                htmlContent: $renderedHtml,
                textContent: $renderedFallback
            );

            // Send the email
            $recipientName = $this->recipientName;
            $recipientEmail = $this->emailLog->recipient_email;

            if ($recipientName !== null) {
                Mail::to([$recipientName => $recipientEmail])->send($message);
            } else {
                Mail::to($recipientEmail)->send($message);
            }

            // Mark as sent
            $this->emailLog->markAsSent();

            Log::info('Templated email sent successfully', [
                'email_log_id' => $this->emailLog->id,
                'template' => $this->template->slug,
                'recipient' => $recipientEmail,
                'attempts' => $this->emailLog->attempts,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send templated email', [
                'email_log_id' => $this->emailLog->id,
                'template' => $this->template->slug,
                'recipient' => $this->emailLog->recipient_email,
                'attempt' => $this->emailLog->attempts,
                'error' => $e->getMessage(),
            ]);

            // If this is the last attempt, mark as failed
            if ($this->emailLog->attempts >= $this->tries) {
                $this->emailLog->markAsFailed($e->getMessage());
            }

            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $this->emailLog->markAsFailed($exception->getMessage());

        Log::error('Templated email job failed permanently', [
            'email_log_id' => $this->emailLog->id,
            'template' => $this->template->slug,
            'recipient' => $this->emailLog->recipient_email,
            'error' => $exception->getMessage(),
        ]);
    }
}
