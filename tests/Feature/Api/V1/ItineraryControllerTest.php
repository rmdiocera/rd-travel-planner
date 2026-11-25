<?php

declare(strict_types=1);

use App\Models\Itinerary;
use App\Models\ItineraryList;
use App\Models\ItineraryListItem;
use App\Models\ItineraryListItemChecklistItem;
use App\Models\ItinerarySpot;
use App\Models\User;

test('unauthenticated users cannot access itinerary endpoints', function () {
    $itinerary = Itinerary::factory()->create();

    $this->getJson('/api/v1/itineraries')->assertUnauthorized();
    $this->postJson('/api/v1/itineraries')->assertUnauthorized();
    $this->getJson("/api/v1/itineraries/{$itinerary->id}")->assertUnauthorized();
    $this->putJson("/api/v1/itineraries/{$itinerary->id}")->assertUnauthorized();
    $this->patchJson("/api/v1/itineraries/{$itinerary->id}")->assertUnauthorized();
    $this->deleteJson("/api/v1/itineraries/{$itinerary->id}")->assertUnauthorized();
});

test('it returns the authenticated user\'s itineraries', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Itinerary::factory()->count(3)->for($user)->create();
    Itinerary::factory()->count(2)->for($other)->create();

    $this->actingAs($user)
        ->getJson('/api/v1/itineraries')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

test('it is grouped based on its dates by past, ongoing, and upcoming itineraries', function () {
    $user = User::factory()->create();

    Itinerary::factory()->for($user)->createMany([
        [
            'start_date' => '2026-03-15',
            'end_date' => '2026-03-27',
        ],
        [
            'start_date' => fake()->dateTimeBetween('-1 week', 'now'),
            'end_date' => fake()->dateTimeBetween('now', '+2 weeks'),
        ],
        [
            'start_date' => fake()->dateTimeBetween('+2 months', '+3 months'),
            'end_date' => fake()->dateTimeBetween('+3 months', '+4 months'),
        ],
    ]);

    $response = $this->actingAs($user)
        ->getJson('/api/v1/itineraries?grouped=true')
        ->assertJsonCount(1, 'data.past')
        ->assertJsonCount(1, 'data.ongoing')
        ->assertJsonCount(1, 'data.upcoming')
        ->assertJsonCount(0, 'data.unscheduled');

    expect($response->json('data.past.0.end_date'))->toBeLessThan(today()->toDateString());
    expect($response->json('data.ongoing.0.start_date'))->toBeLessThanOrEqual(today()->toDateString());
    expect($response->json('data.ongoing.0.end_date'))->toBeGreaterThanOrEqual(today()->toDateString());
    expect($response->json('data.upcoming.0.start_date'))->toBeGreaterThan(today()->toDateString());
});

test('itineraries missing a date are unscheduled', function (?int $start_offset, ?int $end_offset) {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create([
        'start_date' => $start_offset === null ? null : today()->addDays($start_offset),
        'end_date' => $end_offset === null ? null : today()->addDays($end_offset),
    ]);

    $this->actingAs($user)
        ->getJson('/api/v1/itineraries?grouped=1')
        ->assertOk()
        ->assertJsonCount(1, 'data.unscheduled')
        ->assertJsonPath('data.unscheduled.0.id', $itinerary->id)
        ->assertJsonCount(0, 'data.ongoing')
        ->assertJsonCount(0, 'data.upcoming')
        ->assertJsonCount(0, 'data.past');
})->with([
    'no dates' => [null, null],
    'only a start date' => [7, null],
    'only an end date, in the past' => [null, -7],
    'only an end date, today' => [null, 0],
]);

test('the grouped itineraries have all four groups, empty, for a user with no itineraries', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->getJson('/api/v1/itineraries?grouped=1')
        ->assertOk()
        ->assertExactJson(['data' => ['ongoing' => [], 'upcoming' => [], 'unscheduled' => [], 'past' => []]]);

    expect(array_keys($response->json('data')))->toBe(['ongoing', 'upcoming', 'unscheduled', 'past']);
});

test('unscheduled itineraries are listed newest first', function () {
    $user = User::factory()->create();

    [$first, $second, $third, $fourth] = Itinerary::factory()->for($user)->createMany([
        ['start_date' => null, 'end_date' => null, 'created_at' => '2026-01-10 09:00:00'],
        ['start_date' => null, 'end_date' => null, 'created_at' => '2026-03-01 09:00:00'],
        ['start_date' => null, 'end_date' => null, 'created_at' => '2026-03-01 09:00:00'],
        ['start_date' => null, 'end_date' => null, 'created_at' => '2026-02-01 09:00:00'],
    ]);

    $response = $this->actingAs($user)
        ->getJson('/api/v1/itineraries?grouped=1')
        ->assertOk();

    expect($response->json('data.unscheduled.*.id'))->toBe([$third->id, $second->id, $fourth->id, $first->id]);
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

    $this->assertTrue($user->can('view', $itinerary));

    $this->actingAs($user)
        ->getJson("/api/v1/itineraries/{$itinerary->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $itinerary->id);
});

test('user cannot view another user\'s itinerary', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $itinerary = Itinerary::factory()->for($other)->create();

    $this->assertFalse($user->can('view', $itinerary));

    $this->actingAs($user)
        ->getJson("/api/v1/itineraries/{$itinerary->id}")
        ->assertForbidden();
});

test('user can update their own itinerary', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();

    $this->assertTrue($user->can('update', $itinerary));

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

    $this->assertFalse($user->can('update', $itinerary));

    $this->actingAs($user)
        ->putJson("/api/v1/itineraries/{$itinerary->id}", [
            'name' => 'Hijacked Trip',
        ])
        ->assertForbidden();
});

test('user can delete their own itinerary', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();

    $this->assertTrue($user->can('delete', $itinerary));

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

    $this->assertFalse($user->can('delete', $itinerary));

    $this->actingAs($user)
        ->deleteJson("/api/v1/itineraries/{$itinerary->id}")
        ->assertForbidden();

    expect($itinerary->fresh())->not->toBeNull();
});
