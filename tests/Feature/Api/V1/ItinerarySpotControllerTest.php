<?php

declare(strict_types=1);

use App\Models\Itinerary;
use App\Models\ItinerarySpot;
use App\Models\Place;
use App\Models\User;

test('unauthenticated users cannot access spot endpoints', function () {
    $itinerary = Itinerary::factory()->create();

    $this->getJson("/api/v1/itineraries/{$itinerary->id}/spots")->assertUnauthorized();
});

test('user can list spots for their itinerary', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    ItinerarySpot::factory()->count(3)->for($itinerary)->create();

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

test('user cannot list spots for another user\'s itinerary', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $itinerary = Itinerary::factory()->for($other)->create();

    $this->actingAs($user)
        ->getJson("/api/v1/itineraries/{$itinerary->id}/spots")
        ->assertForbidden();
});

test('user can add a spot to their itinerary', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    $place = Place::factory()->create();

    $this->actingAs($user)
        ->postJson("/api/v1/itineraries/{$itinerary->id}/spots", [
            'place_id' => $place->id,
            'visit_date' => '2026-05-01',
        ])
        ->assertCreated()
        ->assertJsonPath('data.place_id', $place->id);

    expect($itinerary->spots()->count())->toBe(1);
});

test('store rejects a duplicate place in the same itinerary', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    $place = Place::factory()->create();
    ItinerarySpot::factory()->for($itinerary)->for($place)->create();

    $this->actingAs($user)
        ->postJson("/api/v1/itineraries/{$itinerary->id}/spots", [
            'place_id' => $place->id,
            'visit_date' => '2026-05-02',
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

    $this->actingAs($user)
        ->deleteJson("/api/v1/itineraries/{$itinerary->id}/spots/{$spot->id}")
        ->assertNoContent();

    expect($spot->fresh())->toBeNull();
});

test('user cannot delete a spot on another user\'s itinerary', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $itinerary = Itinerary::factory()->for($other)->create();
    $spot = ItinerarySpot::factory()->for($itinerary)->create();

    $this->actingAs($user)
        ->deleteJson("/api/v1/itineraries/{$itinerary->id}/spots/{$spot->id}")
        ->assertForbidden();

    expect($spot->fresh())->not->toBeNull();
});
