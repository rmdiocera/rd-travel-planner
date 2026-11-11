<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

test('sends verification notification', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->post(route('verification.send'))
        ->assertRedirect(route('home'));

    Notification::assertSentTo($user, VerifyEmail::class);
});

test('does not send verification notification if email is verified', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('verification.send'))
        ->assertRedirect(route('dashboard', absolute: false));

    Notification::assertNothingSent();
});

test('verification notification can be resent once every five minutes', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->postJson(route('verification.send'))
        ->assertAccepted();

    $response = $this->actingAs($user)
        ->postJson(route('verification.send'))
        ->assertTooManyRequests();

    expect((int) $response->headers->get('Retry-After'))->toBeBetween(295, 300);
    Notification::assertSentToTimes($user, VerifyEmail::class, 1);
});

test('resend cooldown is per user and not per session or ip', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();
    $otherUser = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->postJson(route('verification.send'))
        ->assertAccepted();

    $this->flushSession();

    $this->actingAs($user)
        ->postJson(route('verification.send'))
        ->assertTooManyRequests();

    $this->actingAs($otherUser)
        ->postJson(route('verification.send'))
        ->assertAccepted();
});

test('verification notification can be resent again after five minutes', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->postJson(route('verification.send'))->assertAccepted();
    $this->actingAs($user)->postJson(route('verification.send'))->assertTooManyRequests();

    $this->travel(5)->minutes();

    $this->actingAs($user)->postJson(route('verification.send'))->assertAccepted();
    Notification::assertSentToTimes($user, VerifyEmail::class, 2);
});

test('registering does not use up the resend cooldown', function () {
    Notification::fake();

    $email = fake()->unique()->safeEmail();

    $this->postJson(route('register.store'), [
        'first_name' => 'New',
        'last_name' => 'Traveler',
        'email' => $email,
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertCreated();

    $this->postJson(route('verification.send'))->assertAccepted();

    $user = User::query()->where('email', $email)->firstOrFail();

    Notification::assertSentToTimes($user, VerifyEmail::class, 2);
});

test('throttled resend response exposes Retry-After to the frontend origin', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    $this->withHeaders(['Origin' => config('cors.allowed_origins')[0]]);

    $this->actingAs($user)->postJson(route('verification.send'))->assertAccepted();

    $this->actingAs($user)
        ->postJson(route('verification.send'))
        ->assertTooManyRequests()
        ->assertHeader('Access-Control-Expose-Headers', 'Retry-After');
});

test('already verified user gets no content from the resend endpoint', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('verification.send'))
        ->assertNoContent();

    Notification::assertNothingSent();
});

test('logged out users cannot resend the verification notification', function () {
    $this->postJson(route('verification.send'))->assertUnauthorized();
});

test('legacy verification link route keeps its own more generous limit', function () {
    $user = User::factory()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $this->actingAs($user)->get($verificationUrl)
        ->assertRedirect(route('dashboard', absolute: false).'?verified=1');

    $this->actingAs($user)->get($verificationUrl)
        ->assertRedirect(route('dashboard', absolute: false).'?verified=1');
});
