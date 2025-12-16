<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ClassRegistration;
use App\Services\MailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Send class modification notification email to a participant.
 *
 * Triggered when admin/staff modifies a class occurrence (time, room, trainer).
 * Uses template slug: 'class_modified'
 *
 * This job handles a single participant - dispatch multiple jobs for bulk notifications.
 */
class SendClassModified implements ShouldQueue
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
     * @param ClassRegistration $registration The participant's registration
     * @param string $modifiedByName Name of the person who made the modification
     * @param array<string, mixed> $changes Array of changes (e.g., old/new start times)
     */
    public function __construct(
        public ClassRegistration $registration,
        public string $modifiedByName,
        public array $changes
    ) {
        $this->onQueue('notifications');
    }

    /**
     * Execute the job.
     */
    public function handle(MailService $mailService): void
    {
        try {
            $client = $this->registration->client;
            $user = $client->user;
            $occurrence = $this->registration->occurrence;
            $classTemplate = $occurrence->template;
            $trainer = $occurrence->trainer;

            $variables = [
                'user' => [
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'class' => [
                    'title' => $classTemplate->title,
                    'starts_at' => $occurrence->starts_at->format('Y-m-d H:i'),
                    'ends_at' => $occurrence->ends_at->format('Y-m-d H:i'),
                    'room' => $occurrence->room->name,
                ],
                'trainer' => [
                    'name' => $trainer->user->name,
                ],
                'modified_by' => $this->modifiedByName,
                'old' => [
                    'starts_at' => $this->changes['old_starts_at'] ?? null,
                    'ends_at' => $this->changes['old_ends_at'] ?? null,
                    'room' => $this->changes['old_room'] ?? null,
                    'trainer' => $this->changes['old_trainer'] ?? null,
                ],
                'new' => [
                    'starts_at' => $this->changes['new_starts_at'] ?? $occurrence->starts_at->format('Y-m-d H:i'),
                    'ends_at' => $this->changes['new_ends_at'] ?? $occurrence->ends_at->format('Y-m-d H:i'),
                    'room' => $this->changes['new_room'] ?? $occurrence->room->name,
                    'trainer' => $this->changes['new_trainer'] ?? $trainer->user->name,
                ],
                'status' => $this->registration->status,
            ];

            $mailService->send(
                templateSlug: 'class_modified',
                recipientEmail: $user->email,
                variables: $variables,
                recipientName: $user->name
            );

            Log::info('Class modified notification email queued', [
                'registration_id' => $this->registration->id,
                'occurrence_id' => $occurrence->id,
                'user_id' => $user->id,
                'modified_by' => $this->modifiedByName,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send class modified notification email', [
                'registration_id' => $this->registration->id,
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
        Log::error('Class modified notification job failed permanently', [
            'registration_id' => $this->registration->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
