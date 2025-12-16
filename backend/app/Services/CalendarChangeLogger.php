<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CalendarChangeLog;
use App\Models\Event;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CalendarChangeLogger
{
    /**
     * Log an event creation.
     */
    public function logCreated(Event $event, ?User $actor = null): void
    {
        try {
            $snapshot = $this->createSnapshot($event);
            $actor = $actor ?? $this->getActorFromRequest();

            CalendarChangeLog::create([
                'changed_at' => Carbon::now('UTC'),
                'action' => 'EVENT_CREATED',
                'entity_type' => 'event',
                'entity_id' => $event->id,
                'actor_user_id' => $actor?->id ?? $event->staff_id,
                'actor_name' => $actor?->name ?? $event->staff?->user?->name ?? 'System',
                'actor_role' => $actor ? $this->getUserRole($actor) : 'staff',
                'site' => $this->getSiteName($event),
                'room_id' => $event->room_id,
                'room_name' => $event->room?->name,
                'starts_at' => $event->starts_at,
                'ends_at' => $event->ends_at,
                'before_json' => null,
                'after_json' => $snapshot,
                'changed_fields' => null,
                'ip_address' => $this->getIpAddress(),
                'user_agent' => $this->getUserAgent(),
            ]);
        } catch (\Exception $e) {
            // Log error but don't fail the main operation
            Log::error('Failed to log calendar change (created)', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Log an event update.
     */
    public function logUpdated(Event $event, array $originalAttributes, ?User $actor = null): void
    {
        try {
            // Create temporary event instance with original values for snapshot
            $originalEvent = new Event($originalAttributes);
            $originalEvent->id = $event->id;
            $originalEvent->exists = true;

            // Load relationships for original state if they exist
            if (isset($originalAttributes['room_id'])) {
                $originalEvent->setRelation('room', $event->room);
            }
            if (isset($originalAttributes['staff_id'])) {
                $originalEvent->setRelation('staff', $event->staff);
            }
            if (isset($originalAttributes['client_id'])) {
                $originalEvent->setRelation('client', $event->client);
            }
            if (isset($originalAttributes['service_type_id'])) {
                $originalEvent->setRelation('serviceType', $event->serviceType);
            }

            $beforeSnapshot = $this->createSnapshot($originalEvent);
            $afterSnapshot = $this->createSnapshot($event);
            $changedFields = $this->calculateChangedFields($beforeSnapshot, $afterSnapshot);

            // Only log if there are actual changes
            if (empty($changedFields)) {
                return;
            }

            $actor = $actor ?? $this->getActorFromRequest();

            CalendarChangeLog::create([
                'changed_at' => Carbon::now('UTC'),
                'action' => 'EVENT_UPDATED',
                'entity_type' => 'event',
                'entity_id' => $event->id,
                'actor_user_id' => $actor?->id ?? $event->staff_id,
                'actor_name' => $actor?->name ?? $event->staff?->user?->name ?? 'System',
                'actor_role' => $actor ? $this->getUserRole($actor) : 'staff',
                'site' => $this->getSiteName($event),
                'room_id' => $event->room_id,
                'room_name' => $event->room?->name,
                'starts_at' => $event->starts_at,
                'ends_at' => $event->ends_at,
                'before_json' => $beforeSnapshot,
                'after_json' => $afterSnapshot,
                'changed_fields' => $changedFields,
                'ip_address' => $this->getIpAddress(),
                'user_agent' => $this->getUserAgent(),
            ]);
        } catch (\Exception $e) {
            // Log error but don't fail the main operation
            Log::error('Failed to log calendar change (updated)', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Log an event deletion.
     */
    public function logDeleted(Event $event, ?User $actor = null): void
    {
        try {
            $snapshot = $this->createSnapshot($event);
            $actor = $actor ?? $this->getActorFromRequest();

            CalendarChangeLog::create([
                'changed_at' => Carbon::now('UTC'),
                'action' => 'EVENT_DELETED',
                'entity_type' => 'event',
                'entity_id' => $event->id,
                'actor_user_id' => $actor?->id ?? $event->staff_id,
                'actor_name' => $actor?->name ?? $event->staff?->user?->name ?? 'System',
                'actor_role' => $actor ? $this->getUserRole($actor) : 'staff',
                'site' => $this->getSiteName($event),
                'room_id' => $event->room_id,
                'room_name' => $event->room?->name,
                'starts_at' => $event->starts_at,
                'ends_at' => $event->ends_at,
                'before_json' => $snapshot,
                'after_json' => null,
                'changed_fields' => null,
                'ip_address' => $this->getIpAddress(),
                'user_agent' => $this->getUserAgent(),
            ]);
        } catch (\Exception $e) {
            // Log error but don't fail the main operation
            Log::error('Failed to log calendar change (deleted)', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Create a snapshot of an event's current state.
     */
    protected function createSnapshot(Event $event): array
    {
        return [
            'id' => $event->id,
            'title' => $event->type ?? null, // Events use 'type' field
            'type' => $event->type,
            'starts_at' => $event->starts_at?->toIso8601String(),
            'ends_at' => $event->ends_at?->toIso8601String(),
            'site' => $this->getSiteName($event),
            'room_id' => $event->room_id,
            'room_name' => $event->room?->name,
            'trainer_id' => $event->staff_id,
            'trainer_name' => $event->staff?->user?->name,
            'client_id' => $event->client_id,
            'client_email' => $event->client?->user?->email,
            'service_type_id' => $event->service_type_id,
            'service_type_code' => $event->serviceType?->code,
            'status' => $event->status,
            'attendance_status' => $event->attendance_status,
            'notes' => $event->notes,
            'entry_fee_brutto' => $event->entry_fee_brutto,
            'trainer_fee_brutto' => $event->trainer_fee_brutto,
            'currency' => $event->currency,
        ];
    }

    /**
     * Calculate which fields changed between before and after snapshots.
     */
    protected function calculateChangedFields(array $before, array $after): array
    {
        $changed = [];

        foreach ($after as $key => $value) {
            // Skip if key doesn't exist in before
            if (!array_key_exists($key, $before)) {
                continue;
            }

            // Compare values (handle null values properly)
            if ($before[$key] !== $value) {
                $changed[] = $key;
            }
        }

        return $changed;
    }

    /**
     * Get the current authenticated user or from request.
     */
    protected function getActorFromRequest(): ?User
    {
        return Auth::user() ?? request()->user();
    }

    /**
     * Get the IP address from the current request.
     */
    protected function getIpAddress(): ?string
    {
        if (!request()) {
            return null;
        }

        return request()->ip();
    }

    /**
     * Get the user agent from the current request.
     */
    protected function getUserAgent(): ?string
    {
        if (!request()) {
            return null;
        }

        $userAgent = request()->userAgent();

        // Truncate to fit database column (255 chars)
        return $userAgent ? substr($userAgent, 0, 255) : null;
    }

    /**
     * Get the site name from an event.
     */
    protected function getSiteName(Event $event): ?string
    {
        // If no room_id, can't determine site
        if (!$event->room_id) {
            return null;
        }

        // Load room if not already loaded
        $room = $event->room;
        if (!$room) {
            $room = \App\Models\Room::find($event->room_id);
            if (!$room) {
                return null;
            }
        }

        // First try to get site via site_id (most reliable)
        if ($room->site_id) {
            $site = \App\Models\Site::find($room->site_id);
            if ($site) {
                return $site->name;
            }
        }

        // Fallback to legacy 'site' string attribute on room
        $legacySite = $room->getAttribute('site');
        if ($legacySite && is_string($legacySite)) {
            return $legacySite;
        }

        return null;
    }

    /**
     * Get the role of a user.
     */
    protected function getUserRole(User $user): string
    {
        // Check for admin role
        if ($user->role === 'admin') {
            return 'admin';
        }

        // Check for staff profile
        if ($user->staffProfile) {
            return 'staff';
        }

        // Check for client profile
        if ($user->client) {
            return 'client';
        }

        // Default fallback
        return $user->role ?? 'unknown';
    }
}
