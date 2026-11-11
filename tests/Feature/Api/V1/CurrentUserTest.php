<?php

declare(strict_types=1);

use App\Models\User;

test('unauthenticated users cannot fetch the current user', function () {
    $this->getJson('/api/v1/user')->assertUnauthorized();
});

test('an unverified user can fetch the current user', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->getJson('/api/v1/user')
        ->assertOk()
        ->assertJsonPath('id', $user->id)
        ->assertJsonPath('email_verified_at', null);
});

test('an unverified user is blocked from verified api routes', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->getJson('/api/v1/tags')
        ->assertForbidden();
});
