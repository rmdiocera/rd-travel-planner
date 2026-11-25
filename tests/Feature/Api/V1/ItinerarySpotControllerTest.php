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

test('grouped spots are keyed by date with each day in sort_order', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    createSpotsOnDay($itinerary, '2026-06-10', ['10:00']);
    [$afternoon, $morning] = createSpotsOnDay($itinerary, '2026-06-09', ['14:00', '09:00']);

    $data = $this->actingAs($user)
        ->getJson("/api/v1/itineraries/{$itinerary->id}/spots?group_by_date=1")
        ->assertOk()
        ->json('data');

    expect(array_keys($data))->toBe(['2026-06-09', '2026-06-10']);
    expect(array_column($data['2026-06-09'], 'id'))->toBe([$afternoon->id, $morning->id]);
    expect($data['2026-06-09'][0]['sort_order'])->toBe(1);
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

test('store puts a new spot at the end of its day', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    [$first, $second] = createSpotsOnDay($itinerary, '2026-06-09', [null, null]);
    [$next_day_spot] = createSpotsOnDay($itinerary, '2026-06-10', [null]);

    $response = $this->actingAs($user)
        ->postJson("/api/v1/itineraries/{$itinerary->id}/spots", [
            'place_id' => Place::factory()->create()->id,
            'visit_date' => '2026-06-09',
        ])
        ->assertCreated()
        ->assertJsonPath('data.sort_order', 3)
        ->assertJsonPath('data.marked_visited', false);

    expect(spotIdsBySortOrder($itinerary, '2026-06-09'))->toBe([1 => $first->id, 2 => $second->id, 3 => $response->json('data.id')]);
    expect(spotIdsBySortOrder($itinerary, '2026-06-10'))->toBe([1 => $next_day_spot->id]);

    $this->actingAs($user)
        ->postJson("/api/v1/itineraries/{$itinerary->id}/spots", [
            'place_id' => Place::factory()->create()->id,
            'visit_date' => '2026-06-11',
        ])
        ->assertCreated()
        ->assertJsonPath('data.sort_order', 1);
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

test('moving a spot to another day renumbers the old day and appends it to the new day', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    [$first, $moving, $third] = createSpotsOnDay($itinerary, '2026-06-09', [null, null, null]);
    [$next_day_first, $next_day_second] = createSpotsOnDay($itinerary, '2026-06-10', [null, null]);

    $this->actingAs($user)
        ->patchJson("/api/v1/itineraries/{$itinerary->id}/spots/{$moving->id}", [
            'visit_date' => '2026-06-10',
        ])
        ->assertOk()
        ->assertJsonPath('data.sort_order', 3);

    expect(spotIdsBySortOrder($itinerary, '2026-06-09'))->toBe([1 => $first->id, 2 => $third->id]);
    expect(spotIdsBySortOrder($itinerary, '2026-06-10'))->toBe([1 => $next_day_first->id, 2 => $next_day_second->id, 3 => $moving->id]);
});

test('a visit_date in the API\'s datetime form for the same day does not move the spot', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    [$first, $second, $third] = createSpotsOnDay($itinerary, '2026-06-09', [null, null, null]);

    $this->actingAs($user)
        ->patchJson("/api/v1/itineraries/{$itinerary->id}/spots/{$second->id}", [
            'visit_date' => '2026-06-09T00:00:00.000000Z',
            'marked_visited' => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.sort_order', 2);

    expect(spotIdsBySortOrder($itinerary, '2026-06-09'))->toBe([1 => $first->id, 2 => $second->id, 3 => $third->id]);
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

test('deleting a spot renumbers its day', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    [$deleted, $second, $third] = createSpotsOnDay($itinerary, '2026-06-09', [null, null, null]);
    [$next_day_spot] = createSpotsOnDay($itinerary, '2026-06-10', [null]);

    $this->actingAs($user)
        ->deleteJson("/api/v1/itineraries/{$itinerary->id}/spots/{$deleted->id}")
        ->assertNoContent();

    expect(spotIdsBySortOrder($itinerary, '2026-06-09'))->toBe([1 => $second->id, 2 => $third->id]);
    expect(spotIdsBySortOrder($itinerary, '2026-06-10'))->toBe([1 => $next_day_spot->id]);
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

/**
 * Create one spot per start time on the day, numbered 1..n in that order.
 *
 * @param  list<string|null>  $start_times
 * @return list<ItinerarySpot>
 */
function createSpotsOnDay(Itinerary $itinerary, string $visit_date, array $start_times): array
{
    return collect($start_times)
        ->map(fn (?string $start_time, int $index) => ItinerarySpot::factory()->for($itinerary)->create([
            'visit_date' => $visit_date,
            'start_time' => $start_time,
            'sort_order' => $index + 1,
        ]))
        ->all();
}

/**
 * The day's spot ids keyed by sort_order, so one assertion checks both order and contiguity.
 *
 * @return array<int, string>
 */
function spotIdsBySortOrder(Itinerary $itinerary, string $visit_date): array
{
    return $itinerary->spotsOn($visit_date)->orderBy('sort_order')->pluck('id', 'sort_order')->all();
}
