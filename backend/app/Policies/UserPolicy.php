<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, User $model): bool
    {
        // Users can view their own profile
        if ($user->id === $model->id) {
            return true;
        }

        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $model): bool
    {
        // Users can update their own basic profile
        if ($user->id === $model->id) {
            return true;
        }

        return $user->isAdmin();
    }

    public function delete(User $user, User $model): bool
    {
        // Users can't delete themselves
        if ($user->id === $model->id) {
            return false;
        }

        return $user->isAdmin();
    }

    /**
     * Only admin can change roles.
     */
    public function changeRole(User $user, User $model): bool
    {
        return $user->isAdmin();
    }
}
