<?php

declare(strict_types=1);

use App\Models\Itinerary;
use App\Models\ItineraryList;
use App\Models\ItineraryListItem;
use App\Models\ItineraryListItemChecklistItem;
use App\Models\User;

test('unauthenticated users cannot access list endpoints', function () {
    $itinerary = Itinerary::factory()->create();

    $this->getJson("/api/v1/itineraries/{$itinerary->id}/lists")->assertUnauthorized();
});

test('user can list their itinerary\'s lists', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    ItineraryList::factory()->count(3)->for($itinerary)->create();

    $this->actingAs($user)
        ->getJson("/api/v1/itineraries/{$itinerary->id}/lists")
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

test('user cannot list another user\'s itinerary lists', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $itinerary = Itinerary::factory()->for($other)->create();

    $this->actingAs($user)
        ->getJson("/api/v1/itineraries/{$itinerary->id}/lists")
        ->assertForbidden();
});

test('user can create a list when no lists exist', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();

    $this->actingAs($user)
        ->postJson("/api/v1/itineraries/{$itinerary->id}/lists", [
            'name' => 'Shopping List',
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Shopping List')
        ->assertJsonPath('data.itinerary_id', $itinerary->id)
        ->assertJsonPath('data.sort_order', 1);

    expect($itinerary->lists()->count())->toBe(1);
});

test('user can create a list when lists already exist', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    ItineraryList::factory()->for($itinerary)->create(['sort_order' => 1]);

    $this->actingAs($user)
        ->postJson("/api/v1/itineraries/{$itinerary->id}/lists", [
            'name' => 'Shopping List',
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Shopping List')
        ->assertJsonPath('data.itinerary_id', $itinerary->id)
        ->assertJsonPath('data.sort_order', 2);

    expect($itinerary->lists()->count())->toBe(2);
});

test('store validates required fields', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();

    $this->actingAs($user)
        ->postJson("/api/v1/itineraries/{$itinerary->id}/lists", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('store forbids creating a list on another user\'s itinerary', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $itinerary = Itinerary::factory()->for($other)->create();

    $this->actingAs($user)
        ->postJson("/api/v1/itineraries/{$itinerary->id}/lists", [
            'name' => 'Shopping List',
        ])
        ->assertForbidden();
});

test('user can update their list', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    $list = ItineraryList::factory()->for($itinerary)->create(['name' => 'Old Name']);

    $this->actingAs($user)
        ->putJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}", [
            'name' => 'New Name',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'New Name');

    expect($list->refresh()->name)->toBe('New Name');
});

test('user can reorder lists', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    [$first, $second, $third] = ItineraryList::factory()->for($itinerary)->createMany([
        [
            'name' => 'Places to Visit',
            'sort_order' => 1,
        ],
        [
            'name' => 'Things to Buy',
            'sort_order' => 2,
        ],
        [
            'name' => 'Things to Bring',
            'sort_order' => 3,
        ],
    ]);

    $this->actingAs($user)
        ->patchJson("/api/v1/itineraries/{$itinerary->id}/lists/reorder", [
            'list_ids' => [$third->id, $first->id, $second->id],
        ])
        ->assertNoContent();

    expect($third->refresh()->sort_order)->toBe(1);
    expect($first->refresh()->sort_order)->toBe(2);
    expect($second->refresh()->sort_order)->toBe(3);
});

test('user cannot update a list on another user\'s itinerary', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $itinerary = Itinerary::factory()->for($other)->create();
    $list = ItineraryList::factory()->for($itinerary)->create();

    $this->actingAs($user)
        ->putJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}", [
            'name' => 'Hijacked List',
        ])
        ->assertForbidden();
});

test('user can delete their list', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    $list = ItineraryList::factory()->for($itinerary)->create();

    $this->actingAs($user)
        ->deleteJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}")
        ->assertNoContent();

    expect($list->fresh())->toBeNull();
});

test('deleting a list reorders remaining lists', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    [$first, $second, $third] = ItineraryList::factory()->for($itinerary)->createMany([
        [
            'name' => 'Places to Visit',
            'sort_order' => 1,
        ],
        [
            'name' => 'Things to Buy',
            'sort_order' => 2,
        ],
        [
            'name' => 'Things to Bring',
            'sort_order' => 3,
        ],
    ]);

    $this->actingAs($user)
        ->deleteJson("/api/v1/itineraries/{$itinerary->id}/lists/{$first->id}")
        ->assertNoContent();

    expect($first->fresh())->toBeNull();
    expect($second->refresh()->sort_order)->toBe(1);
    expect($third->refresh()->sort_order)->toBe(2);
});

test('deleting a list deletes its items', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    $list = ItineraryList::factory()->for($itinerary)->create();
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
    [$first_cl_item, $second_cl_item, $third_cl_item] = ItineraryListItemChecklistItem::factory()->for($second, 'item')->createMany([
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
        ->deleteJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}")
        ->assertNoContent();

    expect($first->fresh())->toBeNull();
    expect($second->fresh())->toBeNull();
    expect($third->fresh())->toBeNull();
    expect($first_cl_item->fresh())->toBeNull();
    expect($second_cl_item->fresh())->toBeNull();
    expect($third_cl_item->fresh())->toBeNull();
});

test('user cannot delete a list on another user\'s itinerary', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $itinerary = Itinerary::factory()->for($other)->create();
    $list = ItineraryList::factory()->for($itinerary)->create();

    $this->actingAs($user)
        ->deleteJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}")
        ->assertForbidden();

    expect($list->fresh())->not->toBeNull();
});
