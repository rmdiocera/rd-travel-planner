<?php

use App\Models\User;

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'new.user@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $user = User::query()->where('email', 'new.user@example.com')->firstOrFail();

    expect($user->first_name)->toBe('Test');
    expect($user->last_name)->toBe('User');
});

test('new users can register without a last name', function () {
    $response = $this->post(route('register.store'), [
        'first_name' => 'Test',
        'email' => 'new.user@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $user = User::query()->where('email', 'new.user@example.com')->firstOrFail();

    expect($user->first_name)->toBe('Test');
    expect($user->last_name)->toBeNull();
});

test('first name is required to register', function () {
    $response = $this->post(route('register.store'), [
        'last_name' => 'User',
        'email' => 'new.user@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('first_name');
    $this->assertGuest();
});
