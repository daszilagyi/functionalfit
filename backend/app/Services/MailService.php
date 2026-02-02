<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SendTemplatedEmail;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;

class MailService
{
    /**
     * Default company name for template variables (used when setting not configured).
     */
    private const DEFAULT_COMPANY_NAME = 'FunctionalFit Egeszsegkozpont';

    /**
     * Default support email for template variables (used when setting not configured).
     */
    private const DEFAULT_SUPPORT_EMAIL = 'support@functionalfit.hu';

    /**
     * Retry delays in seconds (15s, 30s, 60s).
     *
     * @var array<int>
     */
    private const RETRY_DELAYS = [15, 30, 60];

    /**
     * Send a templated email to a single recipient.
     *
     * @param string $templateSlug Template identifier
     * @param string $recipientEmail Recipient email address
     * @param array<string, mixed> $variables Variables for placeholder replacement
     * @param string|null $recipientName Optional recipient name for personalization
     *
     * @throws \InvalidArgumentException If template not found or inactive
     */
    public function send(
        string $templateSlug,
        string $recipientEmail,
        array $variables,
        ?string $recipientName = null
    ): void {
        $template = $this->loadTemplate($templateSlug);

        // Merge default variables
        $variables = $this->mergeDefaultVariables($variables, $recipientName);

        // Render subject for logging
        $renderedSubject = $template->renderSubject($variables);

        // Create email log entry with 'queued' status
        $emailLog = EmailLog::create([
            'recipient_email' => $recipientEmail,
            'template_slug' => $templateSlug,
            'subject' => $renderedSubject,
            'payload' => $variables,
            'status' => EmailLog::STATUS_QUEUED,
            'attempts' => 0,
        ]);

        try {
            // Dispatch job to queue with retry delays
            SendTemplatedEmail::dispatch($emailLog, $template, $variables, $recipientName)
                ->onQueue('notifications')
                ->delay(now());

            Log::info('Templated email queued', [
                'email_log_id' => $emailLog->id,
                'template' => $templateSlug,
                'recipient' => $recipientEmail,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to queue templated email', [
                'email_log_id' => $emailLog->id,
                'template' => $templateSlug,
                'recipient' => $recipientEmail,
                'error' => $e->getMessage(),
            ]);

            $emailLog->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Send a templated email to multiple recipients.
     *
     * Useful for class cancellation/modification notifications.
     *
     * @param string $templateSlug Template identifier
     * @param array<array{email: string, name?: string, variables?: array<string, mixed>}> $recipients
     * @param array<string, mixed> $sharedVariables Variables shared across all recipients
     */
    public function sendBulk(
        string $templateSlug,
        array $recipients,
        array $sharedVariables = []
    ): void {
        $template = $this->loadTemplate($templateSlug);

        foreach ($recipients as $recipient) {
            $email = $recipient['email'];
            $name = $recipient['name'] ?? null;

            // Merge shared variables with recipient-specific variables
            $variables = array_merge(
                $sharedVariables,
                $recipient['variables'] ?? []
            );

            try {
                $this->send($templateSlug, $email, $variables, $name);
            } catch (\Exception $e) {
                // Log error but continue with other recipients
                Log::error('Failed to queue bulk email for recipient', [
                    'template' => $templateSlug,
                    'recipient' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Bulk email batch queued', [
            'template' => $templateSlug,
            'total_recipients' => count($recipients),
        ]);
    }

    /**
     * Get available template variables for documentation.
     *
     * @return array<string, string>
     */
    public function getAvailableVariables(): array
    {
        return EmailTemplate::getSupportedVariables();
    }

    /**
     * Get retry delays for the job.
     *
     * @return array<int>
     */
    public static function getRetryDelays(): array
    {
        return self::RETRY_DELAYS;
    }

    /**
     * Preview a template with sample data.
     *
     * @param EmailTemplate $template
     * @param array<string, mixed> $variables
     * @return array{subject: string, html_body: string, fallback_body: ?string}
     */
    public function preview(EmailTemplate $template, array $variables): array
    {
        $variables = $this->mergeDefaultVariables($variables);

        return [
            'subject' => $template->renderSubject($variables),
            'html_body' => $template->render($variables),
            'fallback_body' => $template->renderFallback($variables),
        ];
    }

    /**
     * Send a test email using a template.
     *
     * @param EmailTemplate $template
     * @param string $testEmail
     * @param array<string, mixed> $variables
     */
    public function sendTestEmail(EmailTemplate $template, string $testEmail, array $variables): void
    {
        $this->send($template->slug, $testEmail, $variables, 'Test User');
    }

    /**
     * Load and validate a template by slug.
     *
     * @throws \InvalidArgumentException If template not found or inactive
     */
    private function loadTemplate(string $templateSlug): EmailTemplate
    {
        $template = EmailTemplate::active()->bySlug($templateSlug)->first();

        if ($template === null) {
            throw new \InvalidArgumentException(
                "Email template '{$templateSlug}' not found or is inactive."
            );
        }

        return $template;
    }

    /**
     * Merge default/system variables with user-provided variables.
     *
     * @param array<string, mixed> $variables
     * @param string|null $recipientName
     * @return array<string, mixed>
     */
    private function mergeDefaultVariables(array $variables, ?string $recipientName = null): array
    {
        $defaults = [
            'company_name' => Setting::get('email_company_name', self::DEFAULT_COMPANY_NAME),
            'support_email' => Setting::get('email_support_email', self::DEFAULT_SUPPORT_EMAIL),
            'current_year' => date('Y'),
        ];

        if ($recipientName !== null && !isset($variables['user']['name'])) {
            $defaults['user']['name'] = $recipientName;
        }

        return array_replace_recursive($defaults, $variables);
    }
}
