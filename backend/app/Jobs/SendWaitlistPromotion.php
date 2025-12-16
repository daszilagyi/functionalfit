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

class SendWaitlistPromotion implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;
    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public ClassRegistration $registration
    ) {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        $client = $this->registration->client;
        $occurrence = $this->registration->occurrence;
        $classTemplate = $occurrence->template;

        $notification = Notification::create([
            'user_id' => $client->user_id,
            'template_key' => 'waitlist_promotion',
            'channel' => 'email',
            'payload' => [
                'registration_id' => $this->registration->id,
                'class_title' => $classTemplate->title,
                'starts_at' => $occurrence->starts_at->toISOString(),
            ],
            'status' => 'pending',
        ]);

        try {
            Mail::to($client->user->email)->send(
                new \App\Mail\WaitlistPromotion($this->registration)
            );

            $notification->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            Log::info('Waitlist promotion sent', [
                'registration_id' => $this->registration->id,
            ]);
        } catch (\Exception $e) {
            $notification->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error('Failed to send waitlist promotion', [
                'registration_id' => $this->registration->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
