<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff() || $user->isAdmin();
    }

    public function view(User $user, Client $client): bool
    {
        // Client can view their own profile
        if ($user->isClient() && $client->user_id === $user->id) {
            return true;
        }

        // Staff and admin can view all clients
        return $user->isStaff() || $user->isAdmin();
    }

    public function update(User $user, Client $client): bool
    {
        // Client can update their own profile
        if ($user->isClient() && $client->user_id === $user->id) {
            return true;
        }

        // Admin can update any client
        return $user->isAdmin();
    }

    /**
     * View client activity and pass history.
     */
    public function viewActivity(User $user, Client $client): bool
    {
        // Client can view their own activity
        if ($user->isClient() && $client->user_id === $user->id) {
            return true;
        }

        // Staff assigned to client events can view
        if ($user->isStaff()) {
            // This could be enhanced to check if staff has actually worked with this client
            return true;
        }

        // Admin can view all
        return $user->isAdmin();
    }

    public function delete(User $user, Client $client): bool
    {
        return $user->isAdmin();
    }
}
