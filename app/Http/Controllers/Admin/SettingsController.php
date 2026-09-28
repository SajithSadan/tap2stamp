<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(): Response
    {
        $clientId = (string) config('services.google.client_id');

        return Inertia::render('Admin/Settings', [
            'google' => [
                'enabled' => (bool) Setting::get(Setting::GOOGLE_AUTH, true),
                'configured' => GoogleAuthController::configured(),
                // Not a secret, but no need to show it whole either.
                'client_id_hint' => $clientId !== '' ? substr($clientId, 0, 12).'…' : null,
                // The exact URL to register under "Authorised redirect URIs".
                'redirect_uri' => url(config('services.google.redirect')),
                // Owners with no password can only sign in with Google.
                'google_only_owners' => User::where('role', UserRole::Owner)
                    ->whereNotNull('google_id')
                    ->whereNull('password')
                    ->count(),
            ],
        ]);
    }

    public function updateGoogle(Request $request): RedirectResponse
    {
        $enabled = $request->validate(['enabled' => ['required', 'boolean']])['enabled'];

        if ($enabled && ! GoogleAuthController::configured()) {
            throw ValidationException::withMessages([
                'enabled' => 'Add GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET to .env first.',
            ]);
        }

        Setting::set(Setting::GOOGLE_AUTH, (bool) $enabled);

        return back()->with('status', $enabled ? 'Google sign-in is on.' : 'Google sign-in is off.');
    }
}
