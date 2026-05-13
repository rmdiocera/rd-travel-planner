<?php

namespace App\Policies;

use App\Models\Itinerary;
use App\Models\ItinerarySpot;
use App\Models\User;

class ItinerarySpotPolicy
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
    public function view(User $user, ItinerarySpot $spot): bool
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
    public function update(User $user, ItinerarySpot $spot, Itinerary $itinerary): bool
    {
        return $user->id === $itinerary->user_id && $spot->itinerary_id === $itinerary->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ItinerarySpot $spot, Itinerary $itinerary): bool
    {
        return $user->id === $itinerary->user_id && $spot->itinerary_id === $itinerary->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ItinerarySpot $spot): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ItinerarySpot $spot): bool
    {
        return false;
    }
}
