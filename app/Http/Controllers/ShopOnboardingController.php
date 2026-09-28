<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOnboardingShopRequest;
use App\Support\ShopContact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The one-time "set up your shop" step after a self-service sign-up. Like
 * the dashboard it never takes a shop param: it only ever creates the
 * signed-in owner's own (first and only) shop.
 */
class ShopOnboardingController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        if ($request->user()->shop()->exists()) {
            return redirect()->route('dashboard.index');
        }

        // Contact person + email start as the account's own; the owner can change them.
        return Inertia::render('Onboarding/Shop', [
            'ownerName' => $request->user()->name,
            'ownerEmail' => $request->user()->email,
        ]);
    }

    public function store(StoreOnboardingShopRequest $request): RedirectResponse
    {
        // One shop per owner - a double-submit can't create a second.
        if ($request->user()->shop()->exists()) {
            return redirect()->route('dashboard.index');
        }

        $request->user()->shop()->create([
            ...$request->safe()->only(['name', 'slug', 'max_stamps', 'reward_title']),
            ...ShopContact::attributes($request->validated()),
        ]);

        return redirect()->route('dashboard.index')->with('status', 'Your shop is live. Print your counter QR from Settings to start stamping.');
    }
}
