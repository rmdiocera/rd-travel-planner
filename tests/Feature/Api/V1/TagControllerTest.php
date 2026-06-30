<?php

declare(strict_types=1);

use App\Models\Tag;
use App\Models\User;

test('unauthenticated users cannot access the places index', function () {
    $this->getJson('/api/v1/tags')->assertUnauthorized();
});

test('authenticated user can list all places', function () {
    $user = User::factory()->create();
    Tag::factory()->count(3)->create();

    $this->actingAs($user)
        ->getJson('/api/v1/tags')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});
