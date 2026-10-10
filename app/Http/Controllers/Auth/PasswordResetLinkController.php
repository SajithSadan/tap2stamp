<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Forgot password?": emails a reset link (Laravel's password broker,
 * expires in 60 min, one per minute per account). The answer is the same
 * whether or not the email has an account, so the form can't be used to
 * find out who's registered.
 */
class PasswordResetLinkController extends Controller
{
    public const SENT = "If that email has a TaDa Tap account, we've sent a link to reset the password. It works for 60 minutes - check your spam folder too.";

    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', ['status' => session('status')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email', 'max:255']]);

        // Sent, no such account, or asked again within a minute: the same reply for all.
        Password::sendResetLink($request->only('email'));

        return back()->with('status', self::SENT);
    }
}
