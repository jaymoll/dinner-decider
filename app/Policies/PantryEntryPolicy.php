<?php

namespace App\Policies;

use App\Models\PantryEntry;
use App\Models\User;

class PantryEntryPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PantryEntry $pantryEntry): bool
    {
        return $user->id === $pantryEntry->user_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PantryEntry $pantryEntry): bool
    {
        return $user->id === $pantryEntry->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PantryEntry $pantryEntry): bool
    {
        return $user->id === $pantryEntry->user_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, PantryEntry $pantryEntry): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, PantryEntry $pantryEntry): bool
    {
        return false;
    }
}
