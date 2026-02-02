<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\Setting;
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

            // Build the mail instance
            $mail = $recipientName !== null
                ? Mail::to([$recipientName => $recipientEmail])
                : Mail::to($recipientEmail);

            // Add BCC to debug email if enabled
            $debugEmailEnabled = (bool) Setting::get('debug_email_enabled', false);
            $debugEmailAddress = Setting::get('debug_email_address', '');

            if ($debugEmailEnabled && !empty($debugEmailAddress) && filter_var($debugEmailAddress, FILTER_VALIDATE_EMAIL)) {
                $mail->bcc($debugEmailAddress);

                Log::debug('Debug BCC added to email', [
                    'email_log_id' => $this->emailLog->id,
                    'debug_email' => $debugEmailAddress,
                ]);
            }

            // Send the email
            $mail->send($message);

            // Mark as sent
            $this->emailLog->markAsSent();

            Log::info('Templated email sent successfully', [
                'email_log_id' => $this->emailLog->id,
                'template' => $this->template->slug,
                'recipient' => $recipientEmail,
                'attempts' => $this->emailLog->attempts,
                'debug_bcc' => $debugEmailEnabled ? $debugEmailAddress : null,
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
