<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Services\MailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

/**
 * Send registration confirmation email to newly registered users.
 *
 * Triggered after successful user registration.
 * Uses template slug: 'registration_confirmation'
 */
class SendRegistrationConfirmation implements ShouldQueue
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
     */
    public function __construct(
        public User $user
    ) {
        $this->onQueue('notifications');
    }

    /**
     * Execute the job.
     */
    public function handle(MailService $mailService): void
    {
        try {
            // Generate signed confirmation URL (valid for 24 hours)
            $confirmUrl = URL::temporarySignedRoute(
                'verification.verify',
                now()->addHours(24),
                ['id' => $this->user->id, 'hash' => sha1($this->user->email)]
            );

            $variables = [
                'user' => [
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ],
                'confirm_url' => $confirmUrl,
            ];

            $mailService->send(
                templateSlug: 'registration_confirmation',
                recipientEmail: $this->user->email,
                variables: $variables,
                recipientName: $this->user->name
            );

            Log::info('Registration confirmation email queued', [
                'user_id' => $this->user->id,
                'email' => $this->user->email,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send registration confirmation email', [
                'user_id' => $this->user->id,
                'email' => $this->user->email,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Registration confirmation job failed permanently', [
            'user_id' => $this->user->id,
            'email' => $this->user->email,
            'error' => $exception->getMessage(),
        ]);
    }
}
