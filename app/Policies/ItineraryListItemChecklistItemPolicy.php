<?php

namespace App\Policies;

use App\Models\Itinerary;
use App\Models\ItineraryList;
use App\Models\ItineraryListItem;
use App\Models\ItineraryListItemChecklistItem;
use App\Models\User;

class ItineraryListItemChecklistItemPolicy
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
    public function view(User $user, ItineraryListItemChecklistItem $checklistItem): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Itinerary $itinerary, ItineraryList $list, ItineraryListItem $item): bool
    {
        return $user->id === $itinerary->user_id
            && $list->itinerary_id === $itinerary->id
            && $item->itinerary_lists_id === $list->id;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ItineraryListItemChecklistItem $checklistItem, Itinerary $itinerary, ItineraryList $list, ItineraryListItem $item): bool
    {
        return $user->id === $itinerary->user_id
            && $list->itinerary_id === $itinerary->id
            && $item->itinerary_lists_id === $list->id
            && $checklistItem->itinerary_list_item_id === $item->id;
    }

    /**
     * Determine whether the user can reorder the model.
     */
    public function reorder(User $user, Itinerary $itinerary, ItineraryList $list, ItineraryListItem $item): bool
    {
        return $user->id === $itinerary->user_id
            && $list->itinerary_id === $itinerary->id
            && $item->itinerary_lists_id === $list->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ItineraryListItemChecklistItem $checklistItem, Itinerary $itinerary, ItineraryList $list, ItineraryListItem $item): bool
    {
        return $user->id === $itinerary->user_id
            && $list->itinerary_id === $itinerary->id
            && $item->itinerary_lists_id === $list->id
            && $checklistItem->itinerary_list_item_id === $item->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ItineraryListItemChecklistItem $checklistItem): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ItineraryListItemChecklistItem $checklistItem): bool
    {
        return false;
    }
}
