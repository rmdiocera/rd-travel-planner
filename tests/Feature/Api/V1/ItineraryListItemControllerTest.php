<?php

declare(strict_types=1);

use App\Models\Itinerary;
use App\Models\ItineraryList;
use App\Models\ItineraryListItem;
use App\Models\ItineraryListItemChecklistItem;
use App\Models\ItineraryListItemPlace;
use App\Models\Place;
use App\Models\User;

test('unauthenticated users cannot access item endpoints', function () {
    $itinerary = Itinerary::factory()->create();
    $list = ItineraryList::factory()->for($itinerary)->create();

    $this->postJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}/items")->assertUnauthorized();
});

test('user can add a place item to their list', function () {
    $user = User::factory()->create();
    $place = Place::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    $list = ItineraryList::factory()->for($itinerary)->create(['sort_order' => 1]);

    $this->actingAs($user)
        ->postJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}/items", [
            'type' => 'place',
            'place_id' => $place->id,
        ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'place')
        ->assertJsonPath('data.place.place_id', $place->id)
        ->assertJsonPath('data.sort_order', 1);

    expect($list->items()->count())->toBe(1);
});

test('user can add a checklist item to their list', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    $list = ItineraryList::factory()->for($itinerary)->create(['sort_order' => 1]);

    $this->actingAs($user)
        ->postJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}/items", [
            'type' => 'checklist',
        ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'checklist')
        ->assertJsonPath('data.sort_order', 1);

    expect($list->items()->count())->toBe(1);
});

test('user can add a note item to their list', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    $list = ItineraryList::factory()->for($itinerary)->create(['sort_order' => 1]);

    $this->actingAs($user)
        ->postJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}/items", [
            'type' => 'note',
        ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'note')
        ->assertJsonPath('data.sort_order', 1);

    expect($list->items()->count())->toBe(1);
});

test('user can update the start time and end time of a place item on their list', function () {
    $user = User::factory()->create();
    $place = Place::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    $list = ItineraryList::factory()->for($itinerary)->create(['sort_order' => 1]);
    $item = ItineraryListItem::factory()->for($list)->create([
        'type' => 'place',
        'sort_order' => 1,
    ]);
    $place_item = ItineraryListItemPlace::factory()->for($item, 'item')->create([
        'place_id' => $place->id,
    ]);

    $this->actingAs($user)
        ->patchJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}/items/{$item->id}", [
            'type' => 'place',
            'start_time' => '14:00',
            'end_time' => '16:00',
        ])
        ->assertOk()
        ->assertJsonPath('data.type', 'place')
        ->assertJsonPath('data.place.start_time', '14:00:00')
        ->assertJsonPath('data.place.end_time', '16:00:00');

    expect($place_item->refresh()->start_time)->toBe('14:00:00');
    expect($place_item->refresh()->end_time)->toBe('16:00:00');
});

test('user can toggle marked_visited on a place item', function () {
    $user = User::factory()->create();
    $place = Place::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    $list = ItineraryList::factory()->for($itinerary)->create(['sort_order' => 1]);
    $item = ItineraryListItem::factory()->for($list)->create([
        'type' => 'place',
        'sort_order' => 1,
    ]);
    $place_item = ItineraryListItemPlace::factory()->for($item, 'item')->create([
        'place_id' => $place->id,
    ]);

    $this->actingAs($user)
        ->patchJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}/items/{$item->id}", [
            'type' => 'place',
            'marked_visited' => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.type', 'place')
        ->assertJsonPath('data.place.marked_visited', true);

    expect($place_item->refresh()->marked_visited)->toBeTrue();
});

test('user can update the title of a checklist on their list', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    $list = ItineraryList::factory()->for($itinerary)->create(['sort_order' => 1]);
    $item = ItineraryListItem::factory()->for($list)->create([
        'type' => 'checklist',
        'sort_order' => 1,
    ]);

    $this->actingAs($user)
        ->patchJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}/items/{$item->id}", [
            'type' => 'checklist',
            'title' => 'Places to Visit',
        ])
        ->assertOk()
        ->assertJsonPath('data.type', 'checklist')
        ->assertJsonPath('data.title', 'Places to Visit');

    expect($item->refresh()->title)->toBe('Places to Visit');
});

test('user can add an item to their checklist', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    $list = ItineraryList::factory()->for($itinerary)->create(['sort_order' => 1]);
    $item = ItineraryListItem::factory()->for($list)->create([
        'type' => 'checklist',
        'sort_order' => 1,
    ]);

    $this->actingAs($user)
        ->patchJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}/items/{$item->id}", [
            'type' => 'checklist',
            'label' => 'Fushimi Inari',
        ])
        ->assertOk()
        ->assertJsonPath('data.type', 'checklist')
        ->assertJsonPath('data.checklist_items.0.label', 'Fushimi Inari')
        ->assertJsonPath('data.checklist_items.0.sort_order', 1);
});

test('user can add multiple items to their checklist', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    $list = ItineraryList::factory()->for($itinerary)->create(['sort_order' => 1]);
    $item = ItineraryListItem::factory()->for($list)->create([
        'type' => 'checklist',
        'sort_order' => 1,
    ]);

    $this->actingAs($user)
        ->patchJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}/items/{$item->id}", [
            'type' => 'checklist',
            'label' => 'Fushimi Inari',
        ])
        ->assertOk()
        ->assertJsonPath('data.type', 'checklist')
        ->assertJsonPath('data.checklist_items.0.label', 'Fushimi Inari')
        ->assertJsonPath('data.checklist_items.0.sort_order', 1);

    $this->actingAs($user)
        ->patchJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}/items/{$item->id}", [
            'type' => 'checklist',
            'label' => 'Himeji Castle',
        ])
        ->assertOk()
        ->assertJsonPath('data.type', 'checklist')
        ->assertJsonPath('data.checklist_items.1.label', 'Himeji Castle')
        ->assertJsonPath('data.checklist_items.1.sort_order', 2);
});

test('user can change the label of an item on their checklist', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    $list = ItineraryList::factory()->for($itinerary)->create(['sort_order' => 1]);
    $item = ItineraryListItem::factory()->for($list)->create([
        'type' => 'checklist',
        'sort_order' => 1,
    ]);
    $checklist_item = ItineraryListItemChecklistItem::factory()->for($item, 'item')->create([
        'label' => 'Fushimi Inari',
        'sort_order' => 1,
    ]);

    $this->actingAs($user)
        ->patchJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}/items/{$item->id}", [
            'type' => 'checklist',
            'checklist_item_id' => $checklist_item->id,
            'label' => 'Himeji Castle',
        ])
        ->assertOk()
        ->assertJsonPath('data.checklist_items.0.label', 'Himeji Castle');
});

test('user can toggle an item on their checklist', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    $list = ItineraryList::factory()->for($itinerary)->create(['sort_order' => 1]);
    $item = ItineraryListItem::factory()->for($list)->create([
        'type' => 'checklist',
        'sort_order' => 1,
    ]);
    $checklist_item = ItineraryListItemChecklistItem::factory()->for($item, 'item')->create([
        'label' => 'Fushimi Inari',
        'sort_order' => 1,
    ]);

    $this->actingAs($user)
        ->patchJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}/items/{$item->id}", [
            'type' => 'checklist',
            'checklist_item_id' => $checklist_item->id,
            'is_checked' => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.checklist_items.0.is_checked', true);

    $this->actingAs($user)
        ->patchJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}/items/{$item->id}", [
            'type' => 'checklist',
            'checklist_item_id' => $checklist_item->id,
            'is_checked' => false,
        ])
        ->assertOk()
        ->assertJsonPath('data.checklist_items.0.is_checked', false);
});

test('user can update the content of a note on their list', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    $list = ItineraryList::factory()->for($itinerary)->create(['sort_order' => 1]);
    $item = ItineraryListItem::factory()->for($list)->create([
        'type' => 'note',
        'sort_order' => 1,
    ]);

    $this->actingAs($user)
        ->patchJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}/items/{$item->id}", [
            'type' => 'note',
            'content' => 'Bring sunscreen and umbrella',
        ])
        ->assertOk()
        ->assertJsonPath('data.type', 'note')
        ->assertJsonPath('data.content', 'Bring sunscreen and umbrella');

    expect($item->refresh()->content)->toBe('Bring sunscreen and umbrella');
});

test('content is required when updating a note on their list', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    $list = ItineraryList::factory()->for($itinerary)->create(['sort_order' => 1]);
    $item = ItineraryListItem::factory()->for($list)->create([
        'type' => 'note',
        'sort_order' => 1,
    ]);

    $this->actingAs($user)
        ->patchJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}/items/{$item->id}", [
            'type' => 'note',
            'content' => '',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['content']);
});

test('user can reorder list items', function () {
    $user = User::factory()->create();
    $place = Place::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    $list = ItineraryList::factory()->for($itinerary)->create(['sort_order' => 1]);
    [$first, $second, $third] = ItineraryListItem::factory()->for($list)->createMany([
        [
            'type' => 'place',
            'sort_order' => 1,
        ],
        [
            'type' => 'checklist',
            'sort_order' => 2,
        ],
        [
            'type' => 'note',
            'sort_order' => 3,
        ],
    ]);
    ItineraryListItemPlace::factory()->for($first, 'item')->create([
        'place_id' => $place->id,
    ]);

    $this->actingAs($user)
        ->patchJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}/items/reorder", [
            'list_item_ids' => [$third->id, $first->id, $second->id],
        ])
        ->assertNoContent();

    expect($third->refresh()->sort_order)->toBe(1);
    expect($first->refresh()->sort_order)->toBe(2);
    expect($second->refresh()->sort_order)->toBe(3);
});

test('user can reorder checklist items', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    $list = ItineraryList::factory()->for($itinerary)->create(['sort_order' => 1]);
    $item = ItineraryListItem::factory()->for($list)->create([
        'type' => 'checklist',
        'sort_order' => 1,
    ]);
    [$first, $second, $third] = ItineraryListItemChecklistItem::factory()->for($item, 'item')->createMany([
        [
            'label' => 'Fushimi Inari',
            'sort_order' => 1,
        ],
        [
            'label' => 'Himeji Castle',
            'sort_order' => 2,
        ],
        [
            'label' => 'Kiyomizu-dera',
            'sort_order' => 3,
        ],
    ]);

    $this->actingAs($user)
        ->patchJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}/items/{$item->id}/checklist/reorder", [
            'checklist_item_ids' => [$third->id, $first->id, $second->id],
        ])
        ->assertNoContent();

    expect($third->refresh()->sort_order)->toBe(1);
    expect($first->refresh()->sort_order)->toBe(2);
    expect($second->refresh()->sort_order)->toBe(3);
});

test('user can delete a place item from their list', function () {
    $user = User::factory()->create();
    $place = Place::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    $list = ItineraryList::factory()->for($itinerary)->create();
    $item = ItineraryListItem::factory()->for($list)->create([
        'type' => 'place',
        'sort_order' => 1,
    ]);
    $place_item = ItineraryListItemPlace::factory()->for($item, 'item')->create([
        'place_id' => $place->id,
    ]);

    $this->actingAs($user)
        ->deleteJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}/items/{$item->id}")
        ->assertNoContent();

    expect($item->fresh())->toBeNull();
    expect($place_item->fresh())->toBeNull();
});

test('user can delete a checklist item from their list', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    $list = ItineraryList::factory()->for($itinerary)->create();
    $item = ItineraryListItem::factory()->for($list)->create([
        'type' => 'checklist',
        'sort_order' => 1,
    ]);

    $this->actingAs($user)
        ->deleteJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}/items/{$item->id}")
        ->assertNoContent();

    expect($item->fresh())->toBeNull();
});

test('user can delete a checklist item and all its sub-items from their list', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    $list = ItineraryList::factory()->for($itinerary)->create();
    $item = ItineraryListItem::factory()->for($list)->create([
        'type' => 'checklist',
        'sort_order' => 1,
    ]);
    $checklist_item = ItineraryListItemChecklistItem::factory()->for($item, 'item')->create([
        'label' => 'Fushimi Inari',
        'sort_order' => 1,
    ]);

    $this->actingAs($user)
        ->deleteJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}/items/{$item->id}")
        ->assertNoContent();

    expect($item->fresh())->toBeNull();
    expect($checklist_item->fresh())->toBeNull();
});

test('user can delete an item from a checklist', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    $list = ItineraryList::factory()->for($itinerary)->create();
    $item = ItineraryListItem::factory()->for($list)->create([
        'type' => 'checklist',
        'sort_order' => 1,
    ]);
    $checklist_item = ItineraryListItemChecklistItem::factory()->for($item, 'item')->create([
        'label' => 'Fushimi Inari',
        'sort_order' => 1,
    ]);

    $this->actingAs($user)
        ->deleteJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}/items/{$item->id}/checklist/{$checklist_item->id}")
        ->assertNoContent();

    expect($checklist_item->fresh())->toBeNull();
});

test('user can delete a note item from their list', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    $list = ItineraryList::factory()->for($itinerary)->create();
    $item = ItineraryListItem::factory()->for($list)->create([
        'type' => 'note',
        'sort_order' => 1,
    ]);

    $this->actingAs($user)
        ->deleteJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}/items/{$item->id}")
        ->assertNoContent();

    expect($item->fresh())->toBeNull();
});

test('deleting a list item reorders remaining list items', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    $list = ItineraryList::factory()->for($itinerary)->create(['sort_order' => 1]);
    [$first, $second, $third] = ItineraryListItem::factory()->count(3)->for($list)->create();

    $this->actingAs($user)
        ->deleteJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}/items/{$first->id}")
        ->assertNoContent();

    expect($first->fresh())->toBeNull();
    expect($second->refresh()->sort_order)->toBe(1);
    expect($third->refresh()->sort_order)->toBe(2);
});

test('deleting a checklist item reorders remaining checklist items', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    $list = ItineraryList::factory()->for($itinerary)->create(['sort_order' => 1]);
    $item = ItineraryListItem::factory()->for($list)->create([
        'type' => 'checklist',
        'sort_order' => 1,
    ]);
    [$first, $second, $third] = ItineraryListItemChecklistItem::factory()->count(3)->for($item, 'item')->create();

    $this->actingAs($user)
        ->deleteJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}/items/{$item->id}/checklist/{$first->id}")
        ->assertNoContent();

    expect($first->fresh())->toBeNull();
    expect($second->refresh()->sort_order)->toBe(1);
    expect($third->refresh()->sort_order)->toBe(2);
});

test('user cannot delete an item on another user\'s list', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $itinerary = Itinerary::factory()->for($other)->create();
    $list = ItineraryList::factory()->for($itinerary)->create();
    $item = ItineraryListItem::factory()->for($list, 'itineraryList')->create();

    $this->actingAs($user)
        ->deleteJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}/items/{$item->id}")
        ->assertForbidden();

    expect($item->fresh())->not->toBeNull();
});
