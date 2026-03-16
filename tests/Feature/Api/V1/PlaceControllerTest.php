<?php

declare(strict_types=1);

use App\Models\Place;
use App\Models\Tag;
use App\Models\User;

test('unauthenticated users cannot access the places index', function () {
    $this->getJson('/api/v1/places')->assertUnauthorized();
});

test('authenticated user can list all places', function () {
    $user = User::factory()->create();
    Place::factory()->count(3)->create();

    $this->actingAs($user)
        ->getJson('/api/v1/places')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

test('authenticated user can create a place without tags', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/v1/places', [
        'name' => 'Eiffel Tower',
        'details' => 'Famous iron lattice tower in Paris.',
        'address' => 'Champ de Mars, 5 Av. Anatole France, 75007 Paris',
        'country' => 'France',
        'city' => 'Paris',
        'website' => 'https://www.toureiffel.paris',
        'phone' => null,
        'image' => null,
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.name', 'Eiffel Tower');
    $response->assertJsonCount(0, 'data.tags');

    expect(Place::where('name', 'Eiffel Tower')->exists())->toBeTrue();
});

test('authenticated user can create a place with tags', function () {
    $user = User::factory()->create();
    $tags = Tag::factory()->count(2)->create();

    $response = $this->actingAs($user)->postJson('/api/v1/places', [
        'name' => 'Meiji Shrine',
        'details' => 'A Shinto shrine in Tokyo.',
        'address' => '1-1 Yoyogikamizonocho, Shibuya City',
        'country' => 'Japan',
        'city' => 'Tokyo',
        'tags' => $tags->pluck('id')->all(),
    ]);

    $response->assertCreated();
    $response->assertJsonCount(2, 'data.tags');
});

test('store validates required fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/v1/places', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'details', 'address', 'country', 'city']);
});

test('store rejects a duplicate place name', function () {
    $user = User::factory()->create();
    Place::factory()->create(['name' => 'Duplicate Name']);

    $this->actingAs($user)
        ->postJson('/api/v1/places', [
            'name' => 'Duplicate Name',
            'details' => 'Some details.',
            'address' => '123 Street',
            'country' => 'Japan',
            'city' => 'Tokyo',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('store rejects duplicate tag ids', function () {
    $user = User::factory()->create();
    $tag = Tag::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/v1/places', [
            'name' => 'Some Place',
            'details' => 'Some details.',
            'address' => '123 Street',
            'country' => 'Japan',
            'city' => 'Tokyo',
            'tags' => [$tag->id, $tag->id],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tags.0', 'tags.1']);
});

test('authenticated user can view a place', function () {
    $user = User::factory()->create();
    $place = Place::factory()->create();

    $this->actingAs($user)
        ->getJson("/api/v1/places/{$place->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $place->id);
});

test('authenticated user can update a place', function () {
    $user = User::factory()->create();
    $place = Place::factory()->create();
    $tag = Tag::factory()->create();

    $this->actingAs($user)
        ->putJson("/api/v1/places/{$place->id}", [
            'name' => 'Updated Name',
            'details' => 'Updated details.',
            'address' => 'Updated address',
            'country' => 'Japan',
            'city' => 'Tokyo',
            'tags' => [$tag->id],
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Name')
        ->assertJsonCount(1, 'data.tags');

    expect($place->refresh()->name)->toBe('Updated Name');
});

test('update allows the same name on the same record', function () {
    $user = User::factory()->create();
    $place = Place::factory()->create(['name' => 'My Place']);

    $this->actingAs($user)
        ->putJson("/api/v1/places/{$place->id}", [
            'name' => 'My Place',
            'details' => 'Changed details.',
            'address' => 'Changed address',
            'country' => 'Italy',
            'city' => 'Rome',
        ])
        ->assertOk();
});

test('authenticated user can delete a place', function () {
    $user = User::factory()->create();
    $place = Place::factory()->create();

    $this->actingAs($user)
        ->deleteJson("/api/v1/places/{$place->id}")
        ->assertNoContent();

    expect($place->fresh())->toBeNull();
});
