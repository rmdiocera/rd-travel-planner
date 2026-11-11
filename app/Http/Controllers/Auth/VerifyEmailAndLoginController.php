<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyEmailAndLoginRequest;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class VerifyEmailAndLoginController extends Controller
{
    /**
     * Verify the user's email address and, on first verification, log them in when nobody is logged in.
     */
    public function __invoke(VerifyEmailAndLoginRequest $request): RedirectResponse
    {
        $user = $request->verifiableUser();

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));

            $guard = Auth::guard(config('fortify.guard'));

            if ($guard->guest()) {
                $guard->login($user);
                $request->session()->regenerate();
            }
        }

        return redirect()->away(config('app.client_url').'?verified=1');
    }
}
