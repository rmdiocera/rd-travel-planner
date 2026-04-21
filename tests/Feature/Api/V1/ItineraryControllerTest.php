<?php

declare(strict_types=1);

use App\Models\Itinerary;
use App\Models\ItineraryList;
use App\Models\ItineraryListItem;
use App\Models\ItineraryListItemChecklistItem;
use App\Models\ItinerarySpot;
use App\Models\User;

test('unauthenticated users cannot access itinerary endpoints', function () {
    $this->getJson('/api/v1/itineraries')->assertUnauthorized();
});

test('list returns only the authenticated user\'s itineraries', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Itinerary::factory()->count(3)->for($user)->create();
    Itinerary::factory()->count(2)->for($other)->create();

    $this->actingAs($user)
        ->getJson('/api/v1/itineraries')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

test('user can create an itinerary', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/v1/itineraries', [
        'name' => 'Trip to Japan',
        'start_date' => '2026-04-01',
        'end_date' => '2026-04-14',
        'notes' => 'Visit Tokyo and Kyoto.',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.name', 'Trip to Japan');
    $response->assertJsonPath('data.user_id', $user->id);

    expect(Itinerary::where('name', 'Trip to Japan')->exists())->toBeTrue();
});

test('store validates required fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/v1/itineraries', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('store validates end_date is after or equal to start_date', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/v1/itineraries', [
            'name' => 'Trip to Italy',
            'start_date' => '2026-05-10',
            'end_date' => '2026-05-01',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['end_date']);
});

test('user can view their own itinerary', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();

    $this->actingAs($user)
        ->getJson("/api/v1/itineraries/{$itinerary->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $itinerary->id);
});

test('user cannot view another user\'s itinerary', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $itinerary = Itinerary::factory()->for($other)->create();

    $this->actingAs($user)
        ->getJson("/api/v1/itineraries/{$itinerary->id}")
        ->assertForbidden();
});

test('user can update their own itinerary', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();

    $this->actingAs($user)
        ->putJson("/api/v1/itineraries/{$itinerary->id}", [
            'name' => 'Updated Trip',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-15',
            'notes' => 'Updated notes.',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Trip');

    expect($itinerary->refresh()->name)->toBe('Updated Trip');
});

test('user cannot update another user\'s itinerary', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $itinerary = Itinerary::factory()->for($other)->create();

    $this->actingAs($user)
        ->putJson("/api/v1/itineraries/{$itinerary->id}", [
            'name' => 'Hijacked Trip',
        ])
        ->assertForbidden();
});

test('user can delete their own itinerary', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();

    $this->actingAs($user)
        ->deleteJson("/api/v1/itineraries/{$itinerary->id}")
        ->assertNoContent();

    expect($itinerary->fresh())->toBeNull();
});

test('deleting an itinerary deletes its spots, its lists and the items under those lists', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    [$first_spot, $second_spot] = ItinerarySpot::factory()->count(2)->for($itinerary)->create();
    [$first_list, $second_list, $third_list] = ItineraryList::factory()->count(3)->for($itinerary)->create();
    [$first_item, $second_item, $third_item] = ItineraryListItem::factory()->for($first_list)->createMany([
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
    [$first_cl_item, $second_cl_item, $third_cl_item] = ItineraryListItemChecklistItem::factory()->for($second_item, 'item')->createMany([
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
        ->deleteJson("/api/v1/itineraries/{$itinerary->id}")
        ->assertNoContent();

    expect($itinerary->fresh())->toBeNull();
    expect($first_spot->fresh())->toBeNull();
    expect($second_spot->fresh())->toBeNull();
    expect($first_list->fresh())->toBeNull();
    expect($second_list->fresh())->toBeNull();
    expect($third_list->fresh())->toBeNull();
    expect($first_item->fresh())->toBeNull();
    expect($second_item->fresh())->toBeNull();
    expect($third_item->fresh())->toBeNull();
    expect($first_cl_item->fresh())->toBeNull();
    expect($second_cl_item->fresh())->toBeNull();
    expect($third_cl_item->fresh())->toBeNull();
});

test('user cannot delete another user\'s itinerary', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $itinerary = Itinerary::factory()->for($other)->create();

    $this->actingAs($user)
        ->deleteJson("/api/v1/itineraries/{$itinerary->id}")
        ->assertForbidden();

    expect($itinerary->fresh())->not->toBeNull();
});
