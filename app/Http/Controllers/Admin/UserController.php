<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every login on the platform, including owners who signed up but never
 * finished shop setup (the Shops grid can't show them - they have no shop).
 * All rows at once, searched/sorted in the browser like the Shops grid.
 */
class UserController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Users', [
            'users' => User::with('shop:id,user_id,name')
                ->latest()
                ->get()
                ->map(fn (User $user) => self::row($user)),
        ]);
    }

    public static function row(User $user): array
    {
        $owner = $user->role === UserRole::Owner;
        // Step 1 of shop setup, kept on the account until the shop is created.
        $draft = $owner && ! $user->shop ? ($user->onboarding_draft ?? null) : null;
        $phone = $draft && filled($draft['contact_phone'] ?? null)
            ? trim(($draft['contact_phone_code'] ?? null ? '+'.$draft['contact_phone_code'].' ' : '').$draft['contact_phone'])
            : null;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'via_google' => $user->google_id !== null,
            'has_password' => $user->password !== null,
            'created_at' => $user->created_at->toIso8601String(),
            'created_label' => $user->created_at->timezone('Europe/London')->format('j M Y, H:i'),
            'shop' => $user->shop ? ['id' => $user->shop->id, 'name' => $user->shop->name] : null,
            // Owners only: live (has a shop) / business (step 1 saved, shop never
            // created - e.g. the final step failed) / not_started. Null for admins.
            'setup' => match (true) {
                ! $owner => null,
                $user->shop !== null => 'live',
                $draft !== null => 'business',
                default => 'not_started',
            },
            // What they typed in step 1, to follow up with them.
            'draft' => $draft ? [
                'business_name' => $draft['name'] ?? null,
                'contact_name' => $draft['contact_name'] ?? null,
                'phone' => $phone,
                'phone_tel' => $phone ? str_replace(' ', '', $phone) : null,
                'town' => $draft['town'] ?? null,
                'state' => $draft['state'] ?? null,
                'postcode' => $draft['postcode'] ?? null,
            ] : null,
        ];
    }
}
