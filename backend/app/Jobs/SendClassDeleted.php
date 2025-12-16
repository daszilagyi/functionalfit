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
 * Send class deletion notification email to a participant.
 *
 * Triggered when admin/staff deletes a class occurrence.
 * Uses template slug: 'class_deleted'
 *
 * Note: Since the class occurrence may be deleted, we store essential data
 * rather than serializing the models to avoid missing model issues.
 */
class SendClassDeleted implements ShouldQueue
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
     * Participant data stored to avoid issues with deleted models.
     *
     * @var array{user_id: int, name: string, email: string, registration_status: string}
     */
    public array $participantData;

    /**
     * Class data stored to avoid issues with deleted models.
     *
     * @var array{occurrence_id: int, title: string, starts_at: string, ends_at: string, room: string, trainer: string}
     */
    public array $classData;

    /**
     * Name of the admin who deleted the class.
     */
    public string $deletedByName;

    /**
     * Create a new job instance.
     *
     * @param array{user_id: int, name: string, email: string, registration_status: string} $participantData
     * @param array{occurrence_id: int, title: string, starts_at: string, ends_at: string, room: string, trainer: string} $classData
     * @param string $deletedByName Name of the admin who performed deletion
     */
    public function __construct(
        array $participantData,
        array $classData,
        string $deletedByName
    ) {
        $this->onQueue('notifications');
        $this->participantData = $participantData;
        $this->classData = $classData;
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
                    'name' => $this->participantData['name'],
                    'email' => $this->participantData['email'],
                ],
                'class' => [
                    'title' => $this->classData['title'],
                    'starts_at' => $this->classData['starts_at'],
                    'ends_at' => $this->classData['ends_at'],
                    'room' => $this->classData['room'],
                ],
                'trainer' => [
                    'name' => $this->classData['trainer'],
                ],
                'deleted_by' => $this->deletedByName,
                'status' => $this->participantData['registration_status'],
            ];

            $mailService->send(
                templateSlug: 'class_deleted',
                recipientEmail: $this->participantData['email'],
                variables: $variables,
                recipientName: $this->participantData['name']
            );

            Log::info('Class deleted notification email queued', [
                'occurrence_id' => $this->classData['occurrence_id'],
                'user_id' => $this->participantData['user_id'],
                'deleted_by' => $this->deletedByName,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send class deleted notification email', [
                'occurrence_id' => $this->classData['occurrence_id'],
                'user_id' => $this->participantData['user_id'],
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
        Log::error('Class deleted notification job failed permanently', [
            'occurrence_id' => $this->classData['occurrence_id'],
            'user_id' => $this->participantData['user_id'],
            'error' => $exception->getMessage(),
        ]);
    }
}
