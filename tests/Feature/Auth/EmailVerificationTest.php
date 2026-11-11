<?php

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

function verifyAndLoginUrl(User $user, ?DateTimeInterface $expiresAt = null, ?string $hash = null, ?string $id = null): string
{
    return URL::temporarySignedRoute(
        'verification.login',
        $expiresAt ?? now()->addMinutes(60),
        ['id' => $id ?? $user->id, 'hash' => $hash ?? sha1($user->email)]
    );
}

test('email verification screen can be rendered', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get(route('verification.notice'));

    $response->assertOk();
});

test('email can be verified', function () {
    $user = User::factory()->unverified()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $response = $this->actingAs($user)->get($verificationUrl);

    Event::assertDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
});

test('email is not verified with invalid hash', function () {
    $user = User::factory()->unverified()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1('wrong-email')]
    );

    $this->actingAs($user)->get($verificationUrl);

    Event::assertNotDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('email is not verified with invalid user id', function () {
    $user = User::factory()->unverified()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => 123, 'hash' => sha1($user->email)]
    );

    $this->actingAs($user)->get($verificationUrl);

    Event::assertNotDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('verified user is redirected to dashboard from verification prompt', function () {
    $user = User::factory()->create();

    Event::fake();

    $response = $this->actingAs($user)->get(route('verification.notice'));

    Event::assertNotDispatched(Verified::class);
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('already verified user visiting verification link is redirected without firing event again', function () {
    $user = User::factory()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $this->actingAs($user)->get($verificationUrl)
        ->assertRedirect(route('dashboard', absolute: false).'?verified=1');

    Event::assertNotDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('logged out user is verified and logged in from the verify and login link', function () {
    $user = User::factory()->unverified()->create();

    Event::fake();

    $this->get(verifyAndLoginUrl($user))
        ->assertRedirect(config('app.client_url').'?verified=1');

    Event::assertDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $this->assertAuthenticatedAs($user);
});

test('logged in user is verified from the verify and login link and stays logged in', function () {
    $user = User::factory()->unverified()->create();

    Event::fake();

    $this->actingAs($user)
        ->get(verifyAndLoginUrl($user))
        ->assertRedirect(config('app.client_url').'?verified=1');

    Event::assertDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $this->assertAuthenticatedAs($user);
});

test('verifying from another users session does not switch accounts', function () {
    $linkOwner = User::factory()->unverified()->create();
    $otherUser = User::factory()->unverified()->create();

    Event::fake();

    $this->actingAs($otherUser)
        ->get(verifyAndLoginUrl($linkOwner))
        ->assertRedirect(config('app.client_url').'?verified=1');

    expect($linkOwner->fresh()->hasVerifiedEmail())->toBeTrue();
    expect($otherUser->fresh()->hasVerifiedEmail())->toBeFalse();
    $this->assertAuthenticatedAs($otherUser);
});

test('already verified logged out user is not logged in from the verify and login link', function () {
    $user = User::factory()->create();

    Event::fake();

    $this->get(verifyAndLoginUrl($user))
        ->assertRedirect(config('app.client_url').'?verified=1');

    Event::assertNotDispatched(Verified::class);
    $this->assertGuest();
});

test('verify and login link is rejected with an invalid hash', function () {
    $user = User::factory()->unverified()->create();

    $this->get(verifyAndLoginUrl($user, hash: sha1('wrong-email')))->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
    $this->assertGuest();
});

test('verify and login link is rejected with an unknown user id', function () {
    $user = User::factory()->unverified()->create();

    $this->get(verifyAndLoginUrl($user, id: 'missing-user'))->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
    $this->assertGuest();
});

test('expired verify and login link redirects to the client with a failure flag', function () {
    $user = User::factory()->unverified()->create();

    $this->get(verifyAndLoginUrl($user, now()->subMinute()))
        ->assertRedirect(config('app.client_url').'?verified=0');

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
    $this->assertGuest();
});

test('tampered verify and login link redirects to the client with a failure flag', function () {
    $user = User::factory()->unverified()->create();

    $this->get(verifyAndLoginUrl($user).'&tampered=1')
        ->assertRedirect(config('app.client_url').'?verified=0');

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
    $this->assertGuest();
});

test('invalid signatures on other signed routes are not redirected to the client', function () {
    Route::get('signed-route-for-test', fn () => 'ok')->middleware('signed');

    $this->get('signed-route-for-test')->assertForbidden();
});

test('verification emails link to the verify and login route', function () {
    $user = User::factory()->unverified()->create();

    $url = (new VerifyEmail)->toMail($user)->actionUrl;

    expect(parse_url($url, PHP_URL_PATH))->toBe('/email/verify-and-login/'.$user->id.'/'.sha1($user->email));
    expect($url)->toContain('signature=');
});
