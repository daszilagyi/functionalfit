<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\MailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Send notification email when admin deletes a user account.
 *
 * Triggered when admin deletes user.
 * Uses template slug: 'user_deleted'
 *
 * Note: Since the user is deleted, we store essential data rather than
 * serializing the model to avoid missing model issues.
 */
class SendUserDeleted implements ShouldQueue
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
     * User data stored to avoid issues with soft-deleted models.
     *
     * @var array{id: int, name: string, email: string}
     */
    public array $userData;

    /**
     * Name of the admin who deleted the user.
     */
    public string $deletedByName;

    /**
     * Create a new job instance.
     *
     * @param array{id: int, name: string, email: string} $userData Essential user data
     * @param string $deletedByName Name of the admin who performed deletion
     */
    public function __construct(
        array $userData,
        string $deletedByName
    ) {
        $this->onQueue('notifications');
        $this->userData = $userData;
        $this->deletedByName = $deletedByName;
    }

    /**
     * Execute the job.
     */
    public function handle(MailService $mailService): void
    {
        try {
            $variables = [
                'user' => [
                    'name' => $this->userData['name'],
                    'email' => $this->userData['email'],
                ],
                'deleted_by' => $this->deletedByName,
            ];

            $mailService->send(
                templateSlug: 'user_deleted',
                recipientEmail: $this->userData['email'],
                variables: $variables,
                recipientName: $this->userData['name']
            );

            Log::info('User deleted notification email queued', [
                'user_id' => $this->userData['id'],
                'email' => $this->userData['email'],
                'deleted_by' => $this->deletedByName,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send user deleted notification email', [
                'user_id' => $this->userData['id'],
                'email' => $this->userData['email'],
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
        Log::error('User deleted notification job failed permanently', [
            'user_id' => $this->userData['id'],
            'email' => $this->userData['email'],
            'error' => $exception->getMessage(),
        ]);
    }
}
