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

/**
 * Send password reset email to user.
 *
 * Triggered when user requests a password reset.
 * Uses template slug: 'password_reset'
 */
class SendPasswordReset implements ShouldQueue
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
     * @param User $user The user requesting password reset
     * @param string $token The password reset token
     */
    public function __construct(
        public User $user,
        public string $token
    ) {
        $this->onQueue('notifications');
    }

    /**
     * Execute the job.
     */
    public function handle(MailService $mailService): void
    {
        try {
            // Build password reset URL
            $passwordResetUrl = url(config('app.frontend_url', config('app.url')) . '/reset-password?' . http_build_query([
                'token' => $this->token,
                'email' => $this->user->email,
            ]));

            $variables = [
                'user' => [
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ],
                'password_reset_url' => $passwordResetUrl,
            ];

            $mailService->send(
                templateSlug: 'password_reset',
                recipientEmail: $this->user->email,
                variables: $variables,
                recipientName: $this->user->name
            );

            Log::info('Password reset email queued', [
                'user_id' => $this->user->id,
                'email' => $this->user->email,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send password reset email', [
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
        Log::error('Password reset job failed permanently', [
            'user_id' => $this->user->id,
            'email' => $this->user->email,
            'error' => $exception->getMessage(),
        ]);
    }
}
