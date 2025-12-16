<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Event;
use App\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendEventNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;
    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public Event $event,
        public string $notificationType = 'event_confirmation' // or 'event_update', 'event_cancellation', 'event_reminder'
    ) {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        $client = $this->event->client;

        if (!$client) {
            Log::warning('Event has no client, skipping notification', [
                'event_id' => $this->event->id,
            ]);
            return;
        }

        $notification = Notification::create([
            'user_id' => $client->user_id,
            'template_key' => $this->notificationType,
            'channel' => 'email',
            'payload' => [
                'event_id' => $this->event->id,
                'event_title' => $this->event->title ?? 'Individual Session',
                'starts_at' => $this->event->starts_at->toISOString(),
                'ends_at' => $this->event->ends_at->toISOString(),
                'room_name' => $this->event->room->name ?? 'TBD',
                'staff_name' => $this->event->staff->user->name ?? 'Staff',
            ],
            'status' => 'pending',
        ]);

        try {
            Mail::to($client->user->email)->send(
                new \App\Mail\EventNotification($this->event, $this->notificationType)
            );

            $notification->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            Log::info('Event notification sent', [
                'event_id' => $this->event->id,
                'type' => $this->notificationType,
            ]);
        } catch (\Exception $e) {
            $notification->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error('Failed to send event notification', [
                'event_id' => $this->event->id,
                'type' => $this->notificationType,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function getSubject(): string
    {
        return match ($this->notificationType) {
            'event_confirmation' => "Event Confirmed: {$this->event->title}",
            'event_update' => "Event Updated: {$this->event->title}",
            'event_cancellation' => "Event Cancelled: {$this->event->title}",
            'event_reminder' => "Reminder: {$this->event->title}",
            default => "Event Notification: {$this->event->title}",
        };
    }

    private function getBody(): string
    {
        $baseInfo = sprintf(
            "Event: %s\nDate: %s\nTime: %s - %s\nLocation: %s\nStaff: %s",
            $this->event->title,
            $this->event->starts_at->format('Y-m-d'),
            $this->event->starts_at->format('H:i'),
            $this->event->ends_at->format('H:i'),
            $this->event->room->name,
            $this->event->staff->user->name
        );

        return match ($this->notificationType) {
            'event_confirmation' => "Your 1:1 session has been confirmed.\n\n{$baseInfo}",
            'event_update' => "Your 1:1 session has been updated.\n\n{$baseInfo}",
            'event_cancellation' => "Your 1:1 session has been cancelled.\n\nIf you have any questions, please contact us.",
            'event_reminder' => "Reminder: Your 1:1 session is coming up.\n\n{$baseInfo}",
            default => $baseInfo,
        };
    }
}
