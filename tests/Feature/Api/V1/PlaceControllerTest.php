<?php

declare(strict_types=1);

use App\Models\Place;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

test('index without a search term still lists every place, newest first', function () {
    $user = User::factory()->create();
    Place::factory()->count(10)->create();
    $newest = Place::factory()->create(['created_at' => now()->addMinute()]);

    foreach (['/api/v1/places', '/api/v1/places?search=', '/api/v1/places?search=%20%20'] as $url) {
        $this->actingAs($user)
            ->getJson($url)
            ->assertOk()
            ->assertJsonCount(Place::count(), 'data')
            ->assertJsonPath('data.0.id', $newest->id);
    }
});

test('search matches places by name or city', function () {
    $user = User::factory()->create();
    Place::factory()->create(['name' => 'Zqx Lantern Museum', 'city' => 'Elsewhere']);
    Place::factory()->create(['name' => 'Driftwood Harbor Park', 'city' => 'Zqxville']);
    Place::factory()->create(['name' => 'Quiet Garden', 'city' => 'Elsewhere']);

    $this->actingAs($user)
        ->getJson('/api/v1/places?search=zqx')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data' => [['id', 'name', 'city', 'tags']]])
        ->assertJsonMissing(['name' => 'Quiet Garden']);
});

test('search is case and accent insensitive', function () {
    $user = User::factory()->create();
    Place::factory()->create(['name' => 'Café Zqx']);
    Place::factory()->create(['name' => 'Zqx Bakery']);

    $this->actingAs($user)
        ->getJson('/api/v1/places?search=CAFE%20ZQX')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Café Zqx');
});

test('search requires every word to match the name or the city, in any order', function () {
    $user = User::factory()->create();
    Place::factory()->create(['name' => 'Zqx Castle', 'city' => 'Qwvton']);
    Place::factory()->create(['name' => 'Zqx Moat', 'city' => 'Qwvton']);

    $this->actingAs($user)
        ->getJson('/api/v1/places?search=qwvton%20castle')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Zqx Castle');
});

test('search ranks name prefix matches, then name matches, then city matches', function () {
    $user = User::factory()->create();
    Place::factory()->create(['name' => 'Driftwood Pier', 'city' => 'Zqxville']);
    Place::factory()->create(['name' => 'Museum of Zqx', 'city' => 'Elsewhere']);
    Place::factory()->create(['name' => 'Zqx Tower', 'city' => 'Elsewhere']);
    Place::factory()->create(['name' => 'Zqx Arcade', 'city' => 'Elsewhere']);
    Place::factory()->create(['name' => 'Quiet Garden', 'city' => 'Elsewhere']);

    $response = $this->actingAs($user)
        ->getJson('/api/v1/places?search=zqx')
        ->assertOk();

    expect($response->json('data.*.name'))
        ->toBe(['Zqx Arcade', 'Zqx Tower', 'Museum of Zqx', 'Driftwood Pier']);
});

test('search returns at most 10 places', function () {
    $user = User::factory()->create();
    Place::factory()
        ->count(12)
        ->sequence(fn (Sequence $sequence) => ['name' => "Zqx Stall {$sequence->index}"])
        ->create();

    $this->actingAs($user)
        ->getJson('/api/v1/places?search=zqx%20stall')
        ->assertOk()
        ->assertJsonCount(10, 'data');
});

test('search with no matches returns an empty data array', function () {
    $user = User::factory()->create();
    Place::factory()->create(['name' => 'Zqx Lantern Museum']);

    $this->actingAs($user)
        ->getJson('/api/v1/places?search=zqx-no-such-place')
        ->assertOk()
        ->assertExactJson(['data' => []]);
});

test('search treats percent and underscore literally', function () {
    $user = User::factory()->create();
    Place::factory()->create(['name' => 'Zqx 100% Juice']);
    Place::factory()->create(['name' => 'Zqx 1000 Lanterns']);
    Place::factory()->create(['name' => 'Zqx A_B Cafe']);
    Place::factory()->create(['name' => 'Zqx AxB Cafe']);

    $this->actingAs($user)
        ->getJson('/api/v1/places?search='.urlencode('100%'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Zqx 100% Juice');

    $this->actingAs($user)
        ->getJson('/api/v1/places?search=a_b')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Zqx A_B Cafe');
});

test('search for 0 searches instead of listing every place', function () {
    $user = User::factory()->create();
    Place::factory()->create(['name' => 'Zqx Pier 0']);
    Place::factory()->create(['name' => 'Zqx Garden']);

    $this->actingAs($user)
        ->getJson('/api/v1/places?search=0')
        ->assertOk()
        ->assertJsonFragment(['name' => 'Zqx Pier 0'])
        ->assertJsonMissing(['name' => 'Zqx Garden']);
});

test('search must be a string of at most 100 characters', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/v1/places?search[]=zqx')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('search');

    $this->actingAs($user)
        ->getJson('/api/v1/places?search='.str_repeat('a', 101))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('search');
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

test('authenticated user can create a place without images', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/v1/places', [
        'name' => 'Eiffel Tower',
        'details' => 'Famous iron lattice tower in Paris.',
        'address' => 'Champ de Mars, 5 Av. Anatole France, 75007 Paris',
        'country' => 'France',
        'city' => 'Paris',
        'website' => 'https://www.toureiffel.paris',
        'phone' => null,
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.name', 'Eiffel Tower');
    $response->assertJsonCount(0, 'data.images');

    expect(Place::where('name', 'Eiffel Tower')->exists())->toBeTrue();
});

test('authenticated user can create a place with images', function () {
    $user = User::factory()->create();

    Storage::fake('images');

    $place_images = [
        UploadedFile::fake()->image('place_img1.jpg', '800', '800'),
        UploadedFile::fake()->image('place_img2.png', '1024', '768'),
        UploadedFile::fake()->image('place_img3.webp', '1280', '1024'),
    ];

    $response = $this->actingAs($user)->postJson('/api/v1/places', [
        'name' => 'Eiffel Tower',
        'details' => 'Famous iron lattice tower in Paris.',
        'address' => 'Champ de Mars, 5 Av. Anatole France, 75007 Paris',
        'country' => 'France',
        'city' => 'Paris',
        'website' => 'https://www.toureiffel.paris',
        'phone' => null,
        'images' => $place_images,
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.name', 'Eiffel Tower');
    $response->assertJsonCount(3, 'data.images');

    foreach ($response->json('data.images') as $image) {
        Storage::disk('images')->assertExists($image['path']);
    }
});

test('store returns validation errors if uploaded file is not a valid image', function () {
    $user = User::factory()->create();

    Storage::fake('images');

    $wrong_file = UploadedFile::fake()->create('wrong_file.pdf', '2048');

    $this->actingAs($user)->postJson('/api/v1/places', [
        'name' => 'Eiffel Tower',
        'details' => 'Famous iron lattice tower in Paris.',
        'address' => 'Champ de Mars, 5 Av. Anatole France, 75007 Paris',
        'country' => 'France',
        'city' => 'Paris',
        'website' => 'https://www.toureiffel.paris',
        'phone' => null,
        'images' => $wrong_file,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['images']);
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
