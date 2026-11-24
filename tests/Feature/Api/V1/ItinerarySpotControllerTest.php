<?php

declare(strict_types=1);

use App\Models\Itinerary;
use App\Models\ItinerarySpot;
use App\Models\Place;
use App\Models\User;

test('unauthenticated users cannot access spot endpoints', function () {
    $itinerary = Itinerary::factory()->create();
    $spot = ItinerarySpot::factory()->for($itinerary)->create();

    $this->getJson("/api/v1/itineraries/{$itinerary->id}/spots")->assertUnauthorized();
    $this->postJson("/api/v1/itineraries/{$itinerary->id}/spots")->assertUnauthorized();
    $this->putJson("/api/v1/itineraries/{$itinerary->id}/spots/{$spot->id}")->assertUnauthorized();
    $this->patchJson("/api/v1/itineraries/{$itinerary->id}/spots/{$spot->id}")->assertUnauthorized();
    $this->deleteJson("/api/v1/itineraries/{$itinerary->id}/spots/{$spot->id}")->assertUnauthorized();
});

test('user can list spots for their itinerary', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    ItinerarySpot::factory()->count(3)->for($itinerary)->create();

    $this->assertTrue($user->can('viewAny', [ItinerarySpot::class, $itinerary]));

    $this->actingAs($user)
        ->getJson("/api/v1/itineraries/{$itinerary->id}/spots")
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

test('spots grouped by visit_date are keyed by date with spots ordered by start_time', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();

    ItinerarySpot::factory()->for($itinerary)->create(['visit_date' => '2026-06-09', 'start_time' => '14:00']);
    ItinerarySpot::factory()->for($itinerary)->create(['visit_date' => '2026-06-09', 'start_time' => '09:00']);
    ItinerarySpot::factory()->for($itinerary)->create(['visit_date' => '2026-06-10', 'start_time' => '10:00']);

    $response = $this->actingAs($user)
        ->getJson("/api/v1/itineraries/{$itinerary->id}/spots?group_by_date=true")
        ->assertOk();

    $data = $response->json('data');

    expect(array_keys($data))->toBe(['2026-06-09', '2026-06-10']);
    expect($data['2026-06-09'])->toHaveCount(2);
    expect($data['2026-06-09'][0]['start_time'])->toBe('09:00:00');
    expect($data['2026-06-10'])->toHaveCount(1);
});

test('grouped spots list every visit to the same place', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    $place = Place::factory()->create();
    ItinerarySpot::factory()->count(2)->for($itinerary)->for($place)->create(['visit_date' => '2026-06-09']);

    $data = $this->actingAs($user)
        ->getJson("/api/v1/itineraries/{$itinerary->id}/spots?group_by_date=true")
        ->assertOk()
        ->json('data');

    expect($data['2026-06-09'])->toHaveCount(2);
    expect(array_column($data['2026-06-09'], 'place_id'))->toBe([$place->id, $place->id]);
});

test('user cannot list spots for another user\'s itinerary', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $itinerary = Itinerary::factory()->for($other)->create();

    $this->assertFalse($user->can('viewAny', [ItinerarySpot::class, $itinerary]));

    $this->actingAs($user)
        ->getJson("/api/v1/itineraries/{$itinerary->id}/spots")
        ->assertForbidden();
});

test('user can add a spot to their itinerary', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    $place = Place::factory()->create();

    $this->assertTrue($user->can('create', [ItinerarySpot::class, $itinerary]));

    $this->actingAs($user)
        ->postJson("/api/v1/itineraries/{$itinerary->id}/spots", [
            'place_id' => $place->id,
            'visit_date' => '2026-05-01',
        ])
        ->assertCreated()
        ->assertJsonPath('data.place_id', $place->id);

    expect($itinerary->spots()->count())->toBe(1);
});

test('store allows the same place to be added to an itinerary more than once', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    $place = Place::factory()->create();
    ItinerarySpot::factory()->for($itinerary)->for($place)->create(['visit_date' => '2026-05-01']);

    foreach (['2026-05-01', '2026-05-02'] as $visit_date) {
        $this->actingAs($user)
            ->postJson("/api/v1/itineraries/{$itinerary->id}/spots", [
                'place_id' => $place->id,
                'visit_date' => $visit_date,
            ])
            ->assertCreated()
            ->assertJsonPath('data.place_id', $place->id);
    }

    expect($itinerary->spots()->where('place_id', $place->id)->count())->toBe(3);
});

test('store rejects a place that does not exist', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();

    $this->actingAs($user)
        ->postJson("/api/v1/itineraries/{$itinerary->id}/spots", [
            'place_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'visit_date' => '2026-05-01',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['place_id']);
});

test('store validates required fields', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();

    $this->actingAs($user)
        ->postJson("/api/v1/itineraries/{$itinerary->id}/spots", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['place_id', 'visit_date']);
});

test('store forbids adding a spot to another user\'s itinerary', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $itinerary = Itinerary::factory()->for($other)->create();
    $place = Place::factory()->create();

    $this->assertFalse($user->can('create', [ItinerarySpot::class, $itinerary]));

    $this->actingAs($user)
        ->postJson("/api/v1/itineraries/{$itinerary->id}/spots", [
            'place_id' => $place->id,
            'visit_date' => '2026-05-01',
        ])
        ->assertForbidden();
});

test('user can update a spot', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    $place = Place::factory()->create();
    $spot = ItinerarySpot::factory()->for($itinerary)->for($place)->create([
        'visit_date' => '2026-05-01',
        'marked_visited' => false,
    ]);

    $this->assertTrue($user->can('update', [$spot, $itinerary]));

    $this->actingAs($user)
        ->putJson("/api/v1/itineraries/{$itinerary->id}/spots/{$spot->id}", [
            'visit_date' => '2026-05-10',
            'marked_visited' => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.marked_visited', true);

    expect($spot->refresh()->marked_visited)->toBeTrue();
});

test('user cannot update a spot on another user\'s itinerary', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $itinerary = Itinerary::factory()->for($other)->create();
    $place = Place::factory()->create();
    $spot = ItinerarySpot::factory()->for($itinerary)->for($place)->create();

    $this->assertFalse($user->can('update', [$spot, $itinerary]));

    $this->actingAs($user)
        ->putJson("/api/v1/itineraries/{$itinerary->id}/spots/{$spot->id}", [
            'visit_date' => '2026-05-10',
        ])
        ->assertForbidden();
});

test('user can delete a spot', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    $spot = ItinerarySpot::factory()->for($itinerary)->create();

    $this->assertTrue($user->can('delete', [$spot, $itinerary]));

    $this->actingAs($user)
        ->deleteJson("/api/v1/itineraries/{$itinerary->id}/spots/{$spot->id}")
        ->assertNoContent();

    expect($spot->fresh())->toBeNull();
});

test('deleting one visit to a place keeps the other visits', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    $place = Place::factory()->create();
    [$first_visit, $second_visit] = ItinerarySpot::factory()->count(2)->for($itinerary)->for($place)->create();

    $this->actingAs($user)
        ->deleteJson("/api/v1/itineraries/{$itinerary->id}/spots/{$first_visit->id}")
        ->assertNoContent();

    expect($first_visit->fresh())->toBeNull();
    expect($second_visit->fresh())->not->toBeNull();
});

test('user cannot delete a spot on another user\'s itinerary', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $itinerary = Itinerary::factory()->for($other)->create();
    $spot = ItinerarySpot::factory()->for($itinerary)->create();

    $this->assertFalse($user->can('delete', [$spot, $itinerary]));

    $this->actingAs($user)
        ->deleteJson("/api/v1/itineraries/{$itinerary->id}/spots/{$spot->id}")
        ->assertForbidden();

    expect($spot->fresh())->not->toBeNull();
});
