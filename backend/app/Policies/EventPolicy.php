<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Event;
use App\Models\User;
use Carbon\Carbon;

class EventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive();
    }

    public function view(User $user, Event $event): bool
    {
        // Admins and staff can view all events
        if ($user->isAdmin() || $user->isStaff()) {
            return true;
        }

        // Clients can view their own events
        if ($user->isClient() && $event->client_id === $user->client?->id) {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return ($user->isStaff() || $user->isAdmin()) && $user->isActive();
    }

    public function update(User $user, Event $event): bool
    {
        // Admin can update any event
        if ($user->isAdmin()) {
            return true;
        }

        // Staff can update their own events (including past events)
        if ($user->isStaff() && $event->staff->user_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Check if user can move event across days.
     * Only admin can force-move events to different days.
     */
    public function forceUpdate(User $user, Event $event): bool
    {
        return $user->isAdmin();
    }

    /**
     * Check if staff can move event within same day.
     * This is called after the update policy passes.
     */
    public function sameDayMove(User $user, Event $event, string $newStartsAt): bool
    {
        // Admin can always move
        if ($user->isAdmin()) {
            return true;
        }

        // Staff can only move within the same day
        if ($user->isStaff() && $event->staff->user_id === $user->id) {
            $originalDate = Carbon::parse($event->starts_at);
            $newDate = Carbon::parse($newStartsAt);

            return $originalDate->isSameDay($newDate);
        }

        return false;
    }

    public function delete(User $user, Event $event): bool
    {
        // Admin can delete any event
        if ($user->isAdmin()) {
            return true;
        }

        // Staff can delete their own future events
        if ($user->isStaff() && $event->staff->user_id === $user->id) {
            return $event->starts_at > now();
        }

        return false;
    }
}
