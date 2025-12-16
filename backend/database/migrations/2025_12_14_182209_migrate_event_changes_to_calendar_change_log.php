<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Data Migration: Transfer existing event_changes records to calendar_change_log.
     *
     * This migration:
     * 1. Transfers all records from event_changes to calendar_change_log
     * 2. Maps old action values to new constants
     * 3. Preserves historical data with enhanced structure
     * 4. Does NOT delete event_changes table (for rollback safety)
     */
    public function up(): void
    {
        // Only proceed if event_changes table exists
        if (!Schema::hasTable('event_changes')) {
            return;
        }

        // Only proceed if calendar_change_log table exists
        if (!Schema::hasTable('calendar_change_log')) {
            return;
        }

        // Get all event_changes records
        $eventChanges = DB::table('event_changes')->get();

        foreach ($eventChanges as $change) {
            // Map old action values to new constants
            $action = $this->mapAction($change->action);

            // Get user information for denormalization
            $user = DB::table('users')->find($change->by_user_id);
            $actorName = $user ? $user->name : null;
            $actorRole = $user ? $user->role : null;

            // Get event information if event still exists
            $event = DB::table('events')->find($change->event_id);
            $site = null;
            $roomId = null;
            $roomName = null;
            $startsAt = null;
            $endsAt = null;

            if ($event) {
                $site = $event->site ?? null;
                $roomId = $event->room_id ?? null;
                $startsAt = $event->starts_at ?? null;
                $endsAt = $event->ends_at ?? null;

                // Get room name
                if ($roomId) {
                    $room = DB::table('rooms')->find($roomId);
                    $roomName = $room ? $room->name : null;
                }
            }

            // Prepare before/after JSON based on action and meta
            $meta = json_decode($change->meta ?? '{}', true) ?: [];
            $beforeJson = null;
            $afterJson = null;
            $changedFields = null;

            if ($action === 'EVENT_CREATED') {
                $afterJson = $this->buildEventSnapshot($event);
            } elseif ($action === 'EVENT_DELETED') {
                $beforeJson = $this->buildEventSnapshot($event);
            } elseif ($action === 'EVENT_UPDATED') {
                // Try to extract before/after from meta if available
                if (isset($meta['old']) && isset($meta['new'])) {
                    $beforeJson = $meta['old'];
                    $afterJson = $meta['new'];
                    $changedFields = array_keys(array_diff_assoc($meta['new'], $meta['old']));
                } else {
                    // Fallback: store meta as after_json
                    $afterJson = $meta;
                    if ($event) {
                        $beforeJson = $this->buildEventSnapshot($event);
                    }
                }
            }

            // Insert into calendar_change_log
            DB::table('calendar_change_log')->insert([
                'changed_at' => $change->created_at,
                'action' => $action,
                'entity_type' => 'event',
                'entity_id' => $change->event_id,
                'actor_user_id' => $change->by_user_id,
                'actor_name' => $actorName,
                'actor_role' => $actorRole,
                'site' => $site,
                'room_id' => $roomId,
                'room_name' => $roomName,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'before_json' => $beforeJson ? json_encode($beforeJson) : null,
                'after_json' => $afterJson ? json_encode($afterJson) : null,
                'changed_fields' => $changedFields ? json_encode($changedFields) : null,
                'ip_address' => null, // Not available in old records
                'user_agent' => null, // Not available in old records
                'created_at' => $change->created_at,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * Note: This does NOT restore data back to event_changes.
     * It only deletes the migrated records from calendar_change_log.
     */
    public function down(): void
    {
        // Delete all records that were migrated (identifiable by null ip_address)
        if (Schema::hasTable('calendar_change_log')) {
            DB::table('calendar_change_log')
                ->whereNull('ip_address')
                ->whereNull('user_agent')
                ->delete();
        }
    }

    /**
     * Map old action values to new action constants.
     */
    private function mapAction(string $oldAction): string
    {
        return match (strtolower($oldAction)) {
            'created' => 'EVENT_CREATED',
            'updated', 'moved' => 'EVENT_UPDATED',
            'deleted', 'cancelled' => 'EVENT_DELETED',
            default => 'EVENT_UPDATED', // Fallback
        };
    }

    /**
     * Build event snapshot from event record.
     */
    private function buildEventSnapshot(?object $event): ?array
    {
        if (!$event) {
            return null;
        }

        $snapshot = [
            'id' => $event->id,
            'title' => $event->title ?? null,
            'starts_at' => $event->starts_at ?? null,
            'ends_at' => $event->ends_at ?? null,
            'site' => $event->site ?? null,
            'room_id' => $event->room_id ?? null,
            'status' => $event->status ?? null,
        ];

        // Add service type if available
        if (isset($event->service_type_id)) {
            $snapshot['service_type_id'] = $event->service_type_id;
            $serviceType = DB::table('service_types')->find($event->service_type_id);
            if ($serviceType) {
                $snapshot['service_type_code'] = $serviceType->code ?? null;
            }
        }

        // Add trainer if available
        if (isset($event->trainer_id)) {
            $snapshot['trainer_id'] = $event->trainer_id;
            $trainer = DB::table('users')->find($event->trainer_id);
            if ($trainer) {
                $snapshot['trainer_name'] = $trainer->name ?? null;
            }
        }

        // Add client if available (1:1 events)
        if (isset($event->client_id)) {
            $snapshot['client_id'] = $event->client_id;
            $client = DB::table('users')->find($event->client_id);
            if ($client) {
                $snapshot['client_email'] = $client->email ?? null;
            }
        }

        return $snapshot;
    }
};
