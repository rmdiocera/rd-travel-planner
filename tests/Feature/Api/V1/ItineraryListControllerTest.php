<?php

declare(strict_types=1);

use App\Models\Itinerary;
use App\Models\ItineraryList;
use App\Models\ItineraryListItem;
use App\Models\ItineraryListItemChecklistItem;
use App\Models\ItineraryListItemPlace;
use App\Models\Place;
use App\Models\PlaceImage;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

test('unauthenticated users cannot access list endpoints', function () {
    $itinerary = Itinerary::factory()->create();
    $list = ItineraryList::factory()->for($itinerary)->create();

    $this->getJson("/api/v1/itineraries/{$itinerary->id}/lists")->assertUnauthorized();
    $this->postJson("/api/v1/itineraries/{$itinerary->id}/lists")->assertUnauthorized();
    $this->putJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}")->assertUnauthorized();
    $this->patchJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}")->assertUnauthorized();
    $this->deleteJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}")->assertUnauthorized();
    $this->patchJson("/api/v1/itineraries/{$itinerary->id}/lists/reorder")->assertUnauthorized();
});

test('user can list their itinerary\'s lists', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    ItineraryList::factory()->count(3)->for($itinerary)->create();

    $this->assertTrue($user->can('viewAny', [ItineraryList::class, $itinerary]));

    $this->actingAs($user)
        ->getJson("/api/v1/itineraries/{$itinerary->id}/lists")
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

test('listing lists includes each place item\'s place images', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    $list = ItineraryList::factory()->for($itinerary)->create(['sort_order' => 1]);
    $pictured_place = Place::factory()->create();
    [$first_image, $second_image] = PlaceImage::factory()->count(2)->create(['place_id' => $pictured_place->id])->all();
    $create_place_item = fn (Place $place, int $sort_order): ItineraryListItemPlace => ItineraryListItemPlace::factory()
        ->for(ItineraryListItem::factory()->for($list)->create(['type' => 'place', 'sort_order' => $sort_order]), 'item')
        ->create(['place_id' => $place->id]);
    $create_place_item($pictured_place, 1);
    $create_place_item(Place::factory()->create(), 2);
    ItineraryListItem::factory()->for($list)->create(['type' => 'note', 'sort_order' => 3]);

    $items = $this->actingAs($user)
        ->getJson("/api/v1/itineraries/{$itinerary->id}/lists")
        ->assertOk()
        ->json('data.0.items');

    expect(array_column($items[0]['place']['place']['images'], 'id'))->toBe([$first_image->id, $second_image->id]);
    expect($items[0]['place']['place']['images'][0]['url'])->toBe(asset("images/{$first_image->path}"));
    expect($items[1]['place']['place']['images'])->toBe([]);
    expect($items[2]['place'])->toBeNull();
});

test('listing lists loads place images without a query per place item', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    $list = ItineraryList::factory()->for($itinerary)->create(['sort_order' => 1]);
    $add_pictured_place_items = fn (int $count): Collection => ItineraryListItem::factory()
        ->count($count)
        ->for($list)
        ->create(['type' => 'place'])
        ->each(function (ItineraryListItem $item): void {
            $place_item = ItineraryListItemPlace::factory()->for($item, 'item')->create();
            PlaceImage::factory()->create(['place_id' => $place_item->place_id]);
        });
    $count_queries = function () use ($user, $itinerary): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)
            ->getJson("/api/v1/itineraries/{$itinerary->id}/lists")
            ->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    $add_pictured_place_items(1);
    $queries_for_one_item = $count_queries();
    $add_pictured_place_items(3);

    expect($count_queries())->toBe($queries_for_one_item);
});

test('listing lists still returns place items whose place was deleted', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    $list = ItineraryList::factory()->for($itinerary)->create(['sort_order' => 1]);
    $place = Place::factory()->create();
    $item = ItineraryListItem::factory()->for($list)->create(['type' => 'place', 'sort_order' => 1]);
    ItineraryListItemPlace::factory()->for($item, 'item')->create(['place_id' => $place->id]);
    $place->delete();

    $this->actingAs($user)
        ->getJson("/api/v1/itineraries/{$itinerary->id}/lists")
        ->assertOk()
        ->assertJsonPath('data.0.items.0.id', $item->id)
        ->assertJsonPath('data.0.items.0.place', null);
});

test('user cannot list another user\'s itinerary lists', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $itinerary = Itinerary::factory()->for($other)->create();

    $this->assertFalse($user->can('viewAny', [ItineraryList::class, $itinerary]));

    $this->actingAs($user)
        ->getJson("/api/v1/itineraries/{$itinerary->id}/lists")
        ->assertForbidden();
});

test('user can create a list when no lists exist', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();

    $this->assertTrue($user->can('create', [ItineraryList::class, $itinerary]));

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

    $this->assertTrue($user->can('create', [ItineraryList::class, $itinerary]));

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

    $this->assertFalse($user->can('create', [ItineraryList::class, $itinerary]));

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

    $this->assertTrue($user->can('update', [$list, $itinerary]));

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

    $this->assertTrue($user->can('reorder', [ItineraryList::class, $itinerary]));

    $this->actingAs($user)
        ->patchJson("/api/v1/itineraries/{$itinerary->id}/lists/reorder", [
            'list_ids' => [$third->id, $first->id, $second->id],
        ])
        ->assertNoContent();

    expect($third->refresh()->sort_order)->toBe(1);
    expect($first->refresh()->sort_order)->toBe(2);
    expect($second->refresh()->sort_order)->toBe(3);
});

test('user cannot reorder another user\'s itinerary lists', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $itinerary = Itinerary::factory()->for($other)->create();
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

    $this->assertFalse($user->can('reorder', [ItineraryList::class, $itinerary]));

    $this->actingAs($user)
        ->patchJson("/api/v1/itineraries/{$itinerary->id}/lists/reorder", [
            'list_ids' => [$third->id, $first->id, $second->id],
        ])
        ->assertForbidden();
});

test('user cannot update a list on another user\'s itinerary', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $itinerary = Itinerary::factory()->for($other)->create();
    $list = ItineraryList::factory()->for($itinerary)->create();

    $this->assertFalse($user->can('update', [$list, $itinerary]));

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

    $this->assertTrue($user->can('delete', [$list, $itinerary]));

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

    $this->assertFalse($user->can('delete', [$list, $itinerary]));

    $this->actingAs($user)
        ->deleteJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}")
        ->assertForbidden();

    expect($list->fresh())->not->toBeNull();
});
