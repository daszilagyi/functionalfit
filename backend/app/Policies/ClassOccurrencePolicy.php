<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ClassOccurrence;
use App\Models\User;

class ClassOccurrencePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive();
    }

    public function view(User $user, ClassOccurrence $occurrence): bool
    {
        return $user->isActive();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, ClassOccurrence $occurrence): bool
    {
        // Admin can always update
        if ($user->isAdmin()) {
            return true;
        }

        // Assigned trainer can update their own classes
        if ($user->isStaff() && $occurrence->trainer->user_id === $user->id) {
            // Can't update past classes
            return $occurrence->starts_at > now();
        }

        return false;
    }

    public function delete(User $user, ClassOccurrence $occurrence): bool
    {
        // Only admin can delete class occurrences
        return $user->isAdmin();
    }

    /**
     * Force move to different time/day (admin only).
     */
    public function forceMove(User $user, ClassOccurrence $occurrence): bool
    {
        return $user->isAdmin();
    }
}
