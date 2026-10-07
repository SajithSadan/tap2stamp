<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOnboardingShopRequest;
use App\Http\Requests\ValidateOnboardingBusinessRequest;
use App\Models\Shop;
use App\Support\Countries;
use App\Support\ShopContact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        // Anything the owner has already given is filled in: contact person +
        // email from the account, and `draft` (step 1, once it has passed) so
        // setup resumes on the loyalty card step - after a reload, a logout,
        // an expired session or on another device.
        return Inertia::render('Onboarding/Shop', [
            'ownerName' => $request->user()->name,
            'ownerEmail' => $request->user()->email,
            'draft' => $request->user()->onboarding_draft,
            'countries' => Countries::options(),
        ]);
    }

    /**
     * Step 1 -> step 2: checks the business details and keeps them on the
     * account as a draft. No shop exists until the loyalty card step submits.
     */
    public function validateBusiness(ValidateOnboardingBusinessRequest $request): RedirectResponse
    {
        $request->user()->forceFill(['onboarding_draft' => $request->validated()])->save();

        return back();
    }

    public function store(StoreOnboardingShopRequest $request): RedirectResponse
    {
        // One shop per owner - a double-submit can't create a second.
        if ($request->user()->shop()->exists()) {
            return redirect()->route('dashboard.index');
        }

        $shop = DB::transaction(function () use ($request) {
            $shop = $request->user()->shop()->create([
                ...$request->safe()->only(['name', 'max_stamps', 'reward_title']),
                // Made for them - owners don't pick (or see) their card link.
                'slug' => Shop::uniqueSlug($request->string('name')->value()),
                ...ShopContact::attributes($request->validated()),
            ]);

            $request->user()->forceFill(['onboarding_draft' => null])->save();

            return $shop;
        });

        return redirect()->route('dashboard.index')->with('status', $shop->canOrderProducts()
            ? 'Your shop is live! Order your counter display so customers can tap or scan to join.'
            : "Your shop is live! We'll set up your QR code - you'll find it under QR codes to download and print.");
    }
}
