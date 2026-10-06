<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterOwnerRequest;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Self-service sign-up for shop owners (the landing page's "Start Free").
 * Always creates an owner, never an admin; the shop itself is set up on the
 * next screen (ShopOnboardingController).
 */
class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register', ['googleEnabled' => GoogleAuthController::enabled()]);
    }

    public function store(RegisterOwnerRequest $request): RedirectResponse
    {
        $user = User::create([
            'name' => $request->string('name')->trim()->value(),
            'email' => $request->string('email')->value(),
            'password' => $request->string('password')->value(),
            'role' => UserRole::Owner,
        ]);

        ActivityLogger::record('auth.signed_up', 'Signed up with email', null, $user, null, ActivityLogger::user($user));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('onboarding.create');
    }
}
