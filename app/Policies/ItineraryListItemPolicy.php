<?php

namespace App\Policies;

use App\Models\Itinerary;
use App\Models\ItineraryList;
use App\Models\ItineraryListItem;
use App\Models\User;

class ItineraryListItemPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ItineraryListItem $item): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Itinerary $itinerary, ItineraryList $list): bool
    {
        return $user->id === $itinerary->user_id && $list->itinerary_id === $itinerary->id;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ItineraryListItem $item, Itinerary $itinerary, ItineraryList $list): bool
    {
        return $user->id === $itinerary->user_id
            && $list->itinerary_id === $itinerary->id
            && $list->id === $item->itinerary_lists_id;
    }

    /**
     * Determine whether the user can reorder the model.
     */
    public function reorder(User $user, Itinerary $itinerary, ItineraryList $list): bool
    {
        return $user->id === $itinerary->user_id && $list->itinerary_id === $itinerary->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ItineraryListItem $item, Itinerary $itinerary, ItineraryList $list): bool
    {
        return $user->id === $itinerary->user_id
            && $list->itinerary_id === $itinerary->id
            && $list->id === $item->itinerary_lists_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ItineraryListItem $item): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ItineraryListItem $item): bool
    {
        return false;
    }
}
