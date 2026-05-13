<?php

namespace App\Policies;

use App\Models\Itinerary;
use App\Models\ItineraryList;
use App\Models\User;

class ItineraryListPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user, Itinerary $itinerary): bool
    {
        return $user->id === $itinerary->user_id;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ItineraryList $list): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Itinerary $itinerary): bool
    {
        return $user->id === $itinerary->user_id;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ItineraryList $list, Itinerary $itinerary): bool
    {
        return $user->id === $itinerary->user_id && $list->itinerary_id === $itinerary->id;
    }

    /**
     * Determine whether the user can reorder the model.
     */
    public function reorder(User $user, Itinerary $itinerary): bool
    {
        return $user->id === $itinerary->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ItineraryList $list, Itinerary $itinerary): bool
    {
        return $user->id === $itinerary->user_id && $list->itinerary_id === $itinerary->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ItineraryList $list): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ItineraryList $list): bool
    {
        return false;
    }
}
