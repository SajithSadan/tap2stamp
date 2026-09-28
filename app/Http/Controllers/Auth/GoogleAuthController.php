<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/**
 * "Continue with Google" for shop owners - one flow for both sign-up and
 * log-in: an unknown Google account becomes a new owner, a known one just
 * logs in.
 */
class GoogleAuthController extends Controller
{
    /** Both OAuth credentials are in .env. */
    public static function configured(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    /**
     * The Google button and routes exist only when the keys are configured
     * AND the admin hasn't switched Google off on /admin/settings (on by default).
     */
    public static function enabled(): bool
    {
        return self::configured() && (bool) Setting::get(Setting::GOOGLE_AUTH, true);
    }

    public function redirect(): SymfonyRedirect
    {
        abort_unless(self::enabled(), 404);

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless(self::enabled(), 404);

        try {
            // Also verifies the OAuth state, so a forged callback fails here.
            /** @var \Laravel\Socialite\Two\User $google */
            $google = Socialite::driver('google')->user();
        } catch (Throwable) {
            return $this->fail("Google sign-in didn't complete. Please try again.");
        }

        // Only a Google-verified address may create or claim an account.
        if (! $google->getEmail() || ! ($google->getRaw()['email_verified'] ?? false)) {
            return $this->fail('Your Google account has no verified email address.');
        }

        $email = strtolower($google->getEmail());
        $user = User::where('google_id', $google->getId())->first()
            ?? User::where('email', $email)->first();

        // Admin accounts stay password-only: a Google account is never linked to one.
        if ($user?->isAdmin()) {
            return $this->fail('Admin accounts log in with email and password.');
        }

        if (! $user) {
            $user = User::create([
                'name' => $google->getName() ?: strstr($email, '@', true),
                'email' => $email,
                'google_id' => $google->getId(),
                'role' => UserRole::Owner,
            ]);
        } elseif (! $user->google_id) {
            // An owner who signed up with email/password: link Google to them.
            $user->google_id = $google->getId();
        }

        $user->email_verified_at ??= now();
        $user->save();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended($user->homeUrl());
    }

    private function fail(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}
