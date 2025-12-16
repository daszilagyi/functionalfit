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

class SendBookingConfirmation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Delete the job if its models no longer exist.
     */
    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public ClassRegistration $registration
    ) {
        $this->onQueue('notifications');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $client = $this->registration->client;
        $occurrence = $this->registration->occurrence;
        $classTemplate = $occurrence->template;

        // Create notification record
        $notification = Notification::create([
            'user_id' => $client->user_id,
            'template_key' => 'booking_confirmation',
            'channel' => 'email',
            'payload' => [
                'registration_id' => $this->registration->id,
                'class_title' => $classTemplate->title,
                'starts_at' => $occurrence->starts_at->toISOString(),
                'room_name' => $occurrence->room->name,
                'trainer_name' => $occurrence->trainer->user->name,
            ],
            'status' => 'pending',
        ]);

        try {
            // Send email
            Mail::to($client->user->email)->send(
                new \App\Mail\BookingConfirmation($this->registration)
            );

            // Update notification status
            $notification->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            Log::info('Booking confirmation sent', [
                'registration_id' => $this->registration->id,
                'client_id' => $client->id,
                'occurrence_id' => $occurrence->id,
            ]);
        } catch (\Exception $e) {
            $notification->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error('Failed to send booking confirmation', [
                'registration_id' => $this->registration->id,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Build email body text
     */
    private function buildEmailBody(): string
    {
        $occurrence = $this->registration->occurrence;
        $classTemplate = $occurrence->template;

        return sprintf(
            "Your booking for %s on %s at %s has been confirmed.\n\nLocation: %s\nTrainer: %s\n\nSee you there!",
            $classTemplate->title,
            $occurrence->starts_at->format('Y-m-d'),
            $occurrence->starts_at->format('H:i'),
            $occurrence->room->name,
            $occurrence->trainer->user->name
        );
    }
}
