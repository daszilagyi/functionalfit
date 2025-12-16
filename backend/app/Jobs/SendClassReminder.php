<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ClassRegistration;
use App\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendClassReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;
    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public ClassRegistration $registration,
        public int $hoursBeforeClass = 24
    ) {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        $client = $this->registration->client;
        $occurrence = $this->registration->occurrence;
        $classTemplate = $occurrence->template;

        // Check if registration is cancelled
        if ($this->registration->status === 'cancelled') {
            Log::info('Skipping reminder for cancelled registration', [
                'registration_id' => $this->registration->id,
            ]);
            return;
        }

        // Check if class is still upcoming
        if ($occurrence->starts_at->isPast()) {
            Log::info('Skipping reminder for past class', [
                'registration_id' => $this->registration->id,
            ]);
            return;
        }

        $notification = Notification::create([
            'user_id' => $client->user_id,
            'template_key' => 'class_reminder',
            'channel' => 'email',
            'payload' => [
                'registration_id' => $this->registration->id,
                'class_title' => $classTemplate->title,
                'starts_at' => $occurrence->starts_at->toISOString(),
                'room_name' => $occurrence->room->name,
                'trainer_name' => $occurrence->trainer->user->name,
                'hours_before' => $this->hoursBeforeClass,
            ],
            'status' => 'pending',
        ]);

        try {
            Mail::to($client->user->email)->send(
                new \App\Mail\ClassReminder($this->registration)
            );

            $notification->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            Log::info('Class reminder sent', [
                'registration_id' => $this->registration->id,
                'hours_before' => $this->hoursBeforeClass,
            ]);
        } catch (\Exception $e) {
            $notification->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error('Failed to send class reminder', [
                'registration_id' => $this->registration->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
