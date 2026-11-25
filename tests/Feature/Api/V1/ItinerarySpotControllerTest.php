<?php

declare(strict_types=1);

use App\Models\Itinerary;
use App\Models\ItinerarySpot;
use App\Models\Place;
use App\Models\PlaceImage;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

test('unauthenticated users cannot access spot endpoints', function () {
    $itinerary = Itinerary::factory()->create();
    $spot = ItinerarySpot::factory()->for($itinerary)->create();

    $this->getJson("/api/v1/itineraries/{$itinerary->id}/spots")->assertUnauthorized();
    $this->postJson("/api/v1/itineraries/{$itinerary->id}/spots")->assertUnauthorized();
    $this->putJson("/api/v1/itineraries/{$itinerary->id}/spots/{$spot->id}")->assertUnauthorized();
    $this->patchJson("/api/v1/itineraries/{$itinerary->id}/spots/{$spot->id}")->assertUnauthorized();
    $this->deleteJson("/api/v1/itineraries/{$itinerary->id}/spots/{$spot->id}")->assertUnauthorized();
    $this->patchJson("/api/v1/itineraries/{$itinerary->id}/spots/reorder")->assertUnauthorized();
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
    $create_spot = fn (?string $start_time, int $sort_order) => ItinerarySpot::factory()->for($itinerary)->create([
        'visit_date' => '2026-06-09',
        'start_time' => $start_time,
        'sort_order' => $sort_order,
    ]);
    $third = $create_spot('09:00', 3);
    $second = $create_spot('14:00', 2);
    $first = $create_spot(null, 1);

    $data = $this->actingAs($user)
        ->getJson("/api/v1/itineraries/{$itinerary->id}/spots?group_by_date=1")
        ->assertOk()
        ->json('data');

    expect(array_keys($data))->toBe(['2026-06-09', '2026-06-10']);
    expect(array_column($data['2026-06-09'], 'id'))->toBe([$first->id, $second->id, $third->id]);
    expect(array_column($data['2026-06-09'], 'sort_order'))->toBe([1, 2, 3]);
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

test('grouped spots include each place\'s images in upload order', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    $pictured_place = Place::factory()->create();
    [$first_image, $second_image] = PlaceImage::factory()->count(2)->create(['place_id' => $pictured_place->id])->all();
    $create_spot = fn (Place $place, int $sort_order): ItinerarySpot => ItinerarySpot::factory()->for($itinerary)->for($place)->create([
        'visit_date' => '2026-06-09',
        'sort_order' => $sort_order,
    ]);
    $create_spot($pictured_place, 1);
    $create_spot(Place::factory()->create(), 2);
    $create_spot($pictured_place, 3);

    $day = $this->actingAs($user)
        ->getJson("/api/v1/itineraries/{$itinerary->id}/spots?group_by_date=1")
        ->assertOk()
        ->json('data.2026-06-09');

    expect(array_column($day[0]['place']['images'], 'id'))->toBe([$first_image->id, $second_image->id]);
    expect($day[0]['place']['images'][0]['url'])->toBe(asset("images/{$first_image->path}"));
    expect($day[1]['place']['images'])->toBe([]);
    expect(array_column($day[2]['place']['images'], 'id'))->toBe([$first_image->id, $second_image->id]);
});

test('ungrouped spots include each place\'s images', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    $place = Place::factory()->create();
    $image = PlaceImage::factory()->create(['place_id' => $place->id]);
    ItinerarySpot::factory()->for($itinerary)->for($place)->create();

    $this->actingAs($user)
        ->getJson("/api/v1/itineraries/{$itinerary->id}/spots")
        ->assertOk()
        ->assertJsonPath('data.0.place.images.0.url', asset("images/{$image->path}"));
});

test('grouped spots load place images without a query per spot', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    $add_pictured_spots = fn (int $count): Collection => ItinerarySpot::factory()
        ->count($count)
        ->for($itinerary)
        ->create(['visit_date' => '2026-06-09'])
        ->each(fn (ItinerarySpot $spot) => PlaceImage::factory()->create(['place_id' => $spot->place_id]));
    $count_queries = function () use ($user, $itinerary): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)
            ->getJson("/api/v1/itineraries/{$itinerary->id}/spots?group_by_date=1")
            ->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    $add_pictured_spots(1);
    $queries_for_one_spot = $count_queries();
    $add_pictured_spots(3);

    expect($count_queries())->toBe($queries_for_one_spot);
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

test('the spot store response includes the place\'s images', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    $place = Place::factory()->create();
    [$first_image, $second_image] = PlaceImage::factory()->count(2)->create(['place_id' => $place->id])->all();

    $this->actingAs($user)
        ->postJson("/api/v1/itineraries/{$itinerary->id}/spots", [
            'place_id' => $place->id,
            'visit_date' => '2026-06-09',
        ])
        ->assertCreated()
        ->assertJsonPath('data.place.images.0.url', asset("images/{$first_image->path}"))
        ->assertJsonPath('data.place.images.1.id', $second_image->id);
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

test('the spot update response includes the place\'s images', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    $place = Place::factory()->create();
    $image = PlaceImage::factory()->create(['place_id' => $place->id]);
    $spot = ItinerarySpot::factory()->for($itinerary)->for($place)->create(['visit_date' => '2026-06-09']);
    createSpotsOnDay($itinerary, '2026-06-10', [null]);

    $this->actingAs($user)
        ->patchJson("/api/v1/itineraries/{$itinerary->id}/spots/{$spot->id}", [
            'visit_date' => '2026-06-10',
            'start_time' => '10:00',
            'end_time' => '11:00',
        ])
        ->assertOk()
        ->assertJsonPath('data.sort_order', 2)
        ->assertJsonPath('data.place.images.0.url', asset("images/{$image->path}"));
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

test('a start time later than the next timed spot moves the spot just after it', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    [$shibuya_crossing, $meiji_jingu, $harajuku] = createSpotsOnDay($itinerary, '2026-06-09', ['09:00', '11:00', null]);

    $this->actingAs($user)
        ->patchJson("/api/v1/itineraries/{$itinerary->id}/spots/{$shibuya_crossing->id}", [
            'visit_date' => '2026-06-09',
            'start_time' => '13:00',
            'end_time' => null,
        ])
        ->assertOk()
        ->assertJsonPath('data.sort_order', 2);

    expect(spotIdsBySortOrder($itinerary, '2026-06-09'))->toBe([1 => $meiji_jingu->id, 2 => $shibuya_crossing->id, 3 => $harajuku->id]);
});

test('setting the only start time on a day keeps the spot in place', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    [$shibuya_crossing, $meiji_jingu, $harajuku] = createSpotsOnDay($itinerary, '2026-06-09', [null, null, null]);

    $this->actingAs($user)
        ->patchJson("/api/v1/itineraries/{$itinerary->id}/spots/{$meiji_jingu->id}", [
            'visit_date' => '2026-06-09',
            'start_time' => '11:00',
            'end_time' => null,
        ])
        ->assertOk()
        ->assertJsonPath('data.sort_order', 2);

    expect(spotIdsBySortOrder($itinerary, '2026-06-09'))->toBe([1 => $shibuya_crossing->id, 2 => $meiji_jingu->id, 3 => $harajuku->id]);
});

test('a start time in order with the next timed spot keeps the spot in place', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    [$shibuya_crossing, $meiji_jingu, $harajuku] = createSpotsOnDay($itinerary, '2026-06-09', [null, '11:00', null]);

    $this->actingAs($user)
        ->patchJson("/api/v1/itineraries/{$itinerary->id}/spots/{$shibuya_crossing->id}", [
            'visit_date' => '2026-06-09',
            'start_time' => '09:00',
            'end_time' => null,
        ])
        ->assertOk()
        ->assertJsonPath('data.sort_order', 1);

    expect(spotIdsBySortOrder($itinerary, '2026-06-09'))->toBe([1 => $shibuya_crossing->id, 2 => $meiji_jingu->id, 3 => $harajuku->id]);
});

test('a start time earlier than the timed spots before it moves the spot ahead of each', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    [$meiji_jingu, $shibuya_crossing, $harajuku, $senso_ji] = createSpotsOnDay($itinerary, '2026-06-09', ['11:00', '13:00', null, null]);

    $this->actingAs($user)
        ->patchJson("/api/v1/itineraries/{$itinerary->id}/spots/{$senso_ji->id}", [
            'visit_date' => '2026-06-09',
            'start_time' => '10:00',
            'end_time' => null,
        ])
        ->assertOk()
        ->assertJsonPath('data.sort_order', 1);

    expect(spotIdsBySortOrder($itinerary, '2026-06-09'))->toBe([1 => $senso_ji->id, 2 => $meiji_jingu->id, 3 => $shibuya_crossing->id, 4 => $harajuku->id]);
});

test('resending the unchanged start time does not move the spot', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    [$shibuya_crossing, $senso_ji, $meiji_jingu, $harajuku] = createSpotsOnDay($itinerary, '2026-06-09', ['13:00', '10:00', '11:00', null]);

    $this->actingAs($user)
        ->patchJson("/api/v1/itineraries/{$itinerary->id}/spots/{$shibuya_crossing->id}", [
            'visit_date' => '2026-06-09',
            'start_time' => '13:00',
            'end_time' => '14:00',
            'marked_visited' => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.sort_order', 1)
        ->assertJsonPath('data.start_time', '13:00:00')
        ->assertJsonPath('data.end_time', '14:00:00');

    expect(spotIdsBySortOrder($itinerary, '2026-06-09'))->toBe([1 => $shibuya_crossing->id, 2 => $senso_ji->id, 3 => $meiji_jingu->id, 4 => $harajuku->id]);
});

test('a start time equal to the previous timed spot\'s keeps the spot in place', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    [$first, $second] = createSpotsOnDay($itinerary, '2026-06-09', ['10:00', null]);

    $this->actingAs($user)
        ->patchJson("/api/v1/itineraries/{$itinerary->id}/spots/{$second->id}", [
            'visit_date' => '2026-06-09',
            'start_time' => '10:00',
            'end_time' => null,
        ])
        ->assertOk()
        ->assertJsonPath('data.sort_order', 2);

    expect(spotIdsBySortOrder($itinerary, '2026-06-09'))->toBe([1 => $first->id, 2 => $second->id]);
});

test('a start time equal to the next timed spot\'s keeps the spot in place', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    [$first, $second] = createSpotsOnDay($itinerary, '2026-06-09', [null, '11:00']);

    $this->actingAs($user)
        ->patchJson("/api/v1/itineraries/{$itinerary->id}/spots/{$first->id}", [
            'visit_date' => '2026-06-09',
            'start_time' => '11:00',
            'end_time' => null,
        ])
        ->assertOk()
        ->assertJsonPath('data.sort_order', 1);

    expect(spotIdsBySortOrder($itinerary, '2026-06-09'))->toBe([1 => $first->id, 2 => $second->id]);
});

test('the earlier-neighbour check runs first on a day out of time order', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    [$afternoon, $moving, $morning] = createSpotsOnDay($itinerary, '2026-06-09', ['14:00', null, '09:00']);

    $this->actingAs($user)
        ->patchJson("/api/v1/itineraries/{$itinerary->id}/spots/{$moving->id}", [
            'visit_date' => '2026-06-09',
            'start_time' => '12:00',
            'end_time' => null,
        ])
        ->assertOk()
        ->assertJsonPath('data.sort_order', 1);

    expect(spotIdsBySortOrder($itinerary, '2026-06-09'))->toBe([1 => $moving->id, 2 => $afternoon->id, 3 => $morning->id]);
});

test('clearing a start time keeps the spot in place', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    [$senso_ji, $meiji_jingu, $shibuya_crossing] = createSpotsOnDay($itinerary, '2026-06-09', ['10:00', '11:00', '13:00']);

    $this->actingAs($user)
        ->patchJson("/api/v1/itineraries/{$itinerary->id}/spots/{$meiji_jingu->id}", [
            'visit_date' => '2026-06-09',
            'start_time' => null,
            'end_time' => null,
        ])
        ->assertOk()
        ->assertJsonPath('data.sort_order', 2);

    expect(spotIdsBySortOrder($itinerary, '2026-06-09'))->toBe([1 => $senso_ji->id, 2 => $meiji_jingu->id, 3 => $shibuya_crossing->id]);
});

test('moving a timed spot to another day places it in time order there', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    [$moving] = createSpotsOnDay($itinerary, '2026-06-09', ['10:00']);
    [$morning, $late_morning] = createSpotsOnDay($itinerary, '2026-06-10', ['09:00', '11:00']);

    $this->actingAs($user)
        ->patchJson("/api/v1/itineraries/{$itinerary->id}/spots/{$moving->id}", [
            'visit_date' => '2026-06-10',
            'start_time' => '10:00',
            'end_time' => null,
        ])
        ->assertOk()
        ->assertJsonPath('data.sort_order', 2);

    expect(spotIdsBySortOrder($itinerary, '2026-06-10'))->toBe([1 => $morning->id, 2 => $moving->id, 3 => $late_morning->id]);
    expect(spotIdsBySortOrder($itinerary, '2026-06-09'))->toBe([]);
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

test('deleting a place renumbers each day that had a spot of it', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    $place = Place::factory()->create();
    [$first, $removed, $third] = createSpotsOnDay($itinerary, '2026-06-09', [null, null, null]);
    [$next_day_removed, $next_day_kept] = createSpotsOnDay($itinerary, '2026-06-10', [null, null]);
    $removed->update(['place_id' => $place->id]);
    $next_day_removed->update(['place_id' => $place->id]);

    $this->actingAs($user)
        ->deleteJson("/api/v1/places/{$place->id}")
        ->assertNoContent();

    expect(spotIdsBySortOrder($itinerary, '2026-06-09'))->toBe([1 => $first->id, 2 => $third->id]);
    expect(spotIdsBySortOrder($itinerary, '2026-06-10'))->toBe([1 => $next_day_kept->id]);
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

test('user can reorder a day\'s spots', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    [$senso_ji, $meiji_jingu, $shibuya_crossing, $harajuku] = createSpotsOnDay($itinerary, '2026-06-09', ['10:00', '11:00', '13:00', null]);
    [$next_day_spot] = createSpotsOnDay($itinerary, '2026-06-10', [null]);

    $this->assertTrue($user->can('reorder', [ItinerarySpot::class, $itinerary]));

    $this->actingAs($user)
        ->patchJson("/api/v1/itineraries/{$itinerary->id}/spots/reorder", [
            'visit_date' => '2026-06-09',
            'spot_ids' => [$harajuku->id, $senso_ji->id, $meiji_jingu->id, $shibuya_crossing->id],
        ])
        ->assertNoContent();

    expect(spotIdsBySortOrder($itinerary, '2026-06-09'))->toBe([1 => $harajuku->id, 2 => $senso_ji->id, 3 => $meiji_jingu->id, 4 => $shibuya_crossing->id]);
    expect(spotIdsBySortOrder($itinerary, '2026-06-10'))->toBe([1 => $next_day_spot->id]);
});

test('reorder saves times out of order as sent', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    [$senso_ji, $meiji_jingu, $shibuya_crossing, $harajuku] = createSpotsOnDay($itinerary, '2026-06-09', ['10:00', '11:00', '13:00', null]);

    $this->actingAs($user)
        ->patchJson("/api/v1/itineraries/{$itinerary->id}/spots/reorder", [
            'visit_date' => '2026-06-09',
            'spot_ids' => [$shibuya_crossing->id, $senso_ji->id, $meiji_jingu->id, $harajuku->id],
        ])
        ->assertNoContent();

    expect(spotIdsBySortOrder($itinerary, '2026-06-09'))->toBe([1 => $shibuya_crossing->id, 2 => $senso_ji->id, 3 => $meiji_jingu->id, 4 => $harajuku->id]);
});

test('reorder rejects a list missing a spot of the day', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    [$senso_ji, $meiji_jingu, $shibuya_crossing, $harajuku] = createSpotsOnDay($itinerary, '2026-06-09', ['10:00', '11:00', '13:00', null]);
    [$next_day_spot] = createSpotsOnDay($itinerary, '2026-06-10', [null]);

    $this->actingAs($user)
        ->patchJson("/api/v1/itineraries/{$itinerary->id}/spots/reorder", [
            'visit_date' => '2026-06-09',
            'spot_ids' => [$shibuya_crossing->id, $senso_ji->id, $meiji_jingu->id],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['spot_ids' => 'The spot ids must list every spot on this day exactly once.']);

    expect(spotIdsBySortOrder($itinerary, '2026-06-09'))->toBe([1 => $senso_ji->id, 2 => $meiji_jingu->id, 3 => $shibuya_crossing->id, 4 => $harajuku->id]);
    expect(spotIdsBySortOrder($itinerary, '2026-06-10'))->toBe([1 => $next_day_spot->id]);
});

test('reorder rejects a spot from another day', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    [$senso_ji, $meiji_jingu, $shibuya_crossing, $harajuku] = createSpotsOnDay($itinerary, '2026-06-09', ['10:00', '11:00', '13:00', null]);
    [$next_day_spot] = createSpotsOnDay($itinerary, '2026-06-10', [null]);

    $this->actingAs($user)
        ->patchJson("/api/v1/itineraries/{$itinerary->id}/spots/reorder", [
            'visit_date' => '2026-06-09',
            'spot_ids' => [$harajuku->id, $senso_ji->id, $meiji_jingu->id, $shibuya_crossing->id, $next_day_spot->id],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['spot_ids']);

    expect(spotIdsBySortOrder($itinerary, '2026-06-09'))->toBe([1 => $senso_ji->id, 2 => $meiji_jingu->id, 3 => $shibuya_crossing->id, 4 => $harajuku->id]);
    expect(spotIdsBySortOrder($itinerary, '2026-06-10'))->toBe([1 => $next_day_spot->id]);
});

test('reorder rejects a spot from another itinerary', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    $other_itinerary = Itinerary::factory()->for($user)->create();
    [$senso_ji, $meiji_jingu, $shibuya_crossing, $harajuku] = createSpotsOnDay($itinerary, '2026-06-09', ['10:00', '11:00', '13:00', null]);
    [$other_itinerary_spot] = createSpotsOnDay($other_itinerary, '2026-06-09', [null]);

    $this->actingAs($user)
        ->patchJson("/api/v1/itineraries/{$itinerary->id}/spots/reorder", [
            'visit_date' => '2026-06-09',
            'spot_ids' => [$harajuku->id, $senso_ji->id, $meiji_jingu->id, $shibuya_crossing->id, $other_itinerary_spot->id],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['spot_ids']);

    expect(spotIdsBySortOrder($itinerary, '2026-06-09'))->toBe([1 => $senso_ji->id, 2 => $meiji_jingu->id, 3 => $shibuya_crossing->id, 4 => $harajuku->id]);
    expect($other_itinerary_spot->refresh()->sort_order)->toBe(1);
});

test('reorder rejects a repeated spot', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    [$senso_ji, $meiji_jingu, $shibuya_crossing, $harajuku] = createSpotsOnDay($itinerary, '2026-06-09', ['10:00', '11:00', '13:00', null]);

    $this->actingAs($user)
        ->patchJson("/api/v1/itineraries/{$itinerary->id}/spots/reorder", [
            'visit_date' => '2026-06-09',
            'spot_ids' => [$harajuku->id, $senso_ji->id, $meiji_jingu->id, $shibuya_crossing->id, $senso_ji->id],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['spot_ids' => 'The spot ids must list every spot on this day exactly once.']);

    expect(spotIdsBySortOrder($itinerary, '2026-06-09'))->toBe([1 => $senso_ji->id, 2 => $meiji_jingu->id, 3 => $shibuya_crossing->id, 4 => $harajuku->id]);
});

test('reorder rejects more than 255 spot ids', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    [$senso_ji] = createSpotsOnDay($itinerary, '2026-06-09', ['10:00']);

    $this->actingAs($user)
        ->patchJson("/api/v1/itineraries/{$itinerary->id}/spots/reorder", [
            'visit_date' => '2026-06-09',
            'spot_ids' => collect(range(1, 256))->map(fn () => (string) Str::ulid())->all(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['spot_ids' => 'The spot ids field must not have more than 255 items.']);

    expect(spotIdsBySortOrder($itinerary, '2026-06-09'))->toBe([1 => $senso_ji->id]);
});

test('reorder rejects spot_ids sent as an object', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    [$senso_ji, $meiji_jingu, $shibuya_crossing, $harajuku] = createSpotsOnDay($itinerary, '2026-06-09', ['10:00', '11:00', '13:00', null]);

    $this->actingAs($user)
        ->patchJson("/api/v1/itineraries/{$itinerary->id}/spots/reorder", [
            'visit_date' => '2026-06-09',
            'spot_ids' => [
                'first' => $harajuku->id,
                'second' => $senso_ji->id,
                'third' => $meiji_jingu->id,
                'fourth' => $shibuya_crossing->id,
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['spot_ids']);

    expect(spotIdsBySortOrder($itinerary, '2026-06-09'))->toBe([1 => $senso_ji->id, 2 => $meiji_jingu->id, 3 => $shibuya_crossing->id, 4 => $harajuku->id]);
});

test('reorder validates visit_date and spot_ids', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    [$senso_ji, $meiji_jingu, $shibuya_crossing, $harajuku] = createSpotsOnDay($itinerary, '2026-06-09', ['10:00', '11:00', '13:00', null]);

    $this->actingAs($user)
        ->patchJson("/api/v1/itineraries/{$itinerary->id}/spots/reorder", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['visit_date', 'spot_ids']);

    $this->actingAs($user)
        ->patchJson("/api/v1/itineraries/{$itinerary->id}/spots/reorder", [
            'visit_date' => '2026-6-9',
            'spot_ids' => [$harajuku->id, $senso_ji->id, $meiji_jingu->id, $shibuya_crossing->id],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['visit_date']);

    expect(spotIdsBySortOrder($itinerary, '2026-06-09'))->toBe([1 => $senso_ji->id, 2 => $meiji_jingu->id, 3 => $shibuya_crossing->id, 4 => $harajuku->id]);
});

test('user cannot reorder another user\'s itinerary spots', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $itinerary = Itinerary::factory()->for($other)->create();
    [$senso_ji, $meiji_jingu, $shibuya_crossing, $harajuku] = createSpotsOnDay($itinerary, '2026-06-09', ['10:00', '11:00', '13:00', null]);

    $this->assertFalse($user->can('reorder', [ItinerarySpot::class, $itinerary]));

    $this->actingAs($user)
        ->patchJson("/api/v1/itineraries/{$itinerary->id}/spots/reorder", [
            'visit_date' => '2026-06-09',
            'spot_ids' => [$harajuku->id, $senso_ji->id, $meiji_jingu->id, $shibuya_crossing->id],
        ])
        ->assertForbidden();

    $this->actingAs($user)
        ->patchJson("/api/v1/itineraries/{$itinerary->id}/spots/reorder", [
            'visit_date' => '2026-06-09',
            'spot_ids' => [$shibuya_crossing->id, $senso_ji->id],
        ])
        ->assertForbidden();

    expect(spotIdsBySortOrder($itinerary, '2026-06-09'))->toBe([1 => $senso_ji->id, 2 => $meiji_jingu->id, 3 => $shibuya_crossing->id, 4 => $harajuku->id]);
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
