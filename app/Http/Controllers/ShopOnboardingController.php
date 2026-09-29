<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOnboardingShopRequest;
use App\Http\Requests\ValidateOnboardingBusinessRequest;
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

        DB::transaction(function () use ($request) {
            $request->user()->shop()->create([
                ...$request->safe()->only(['name', 'slug', 'max_stamps', 'reward_title']),
                ...ShopContact::attributes($request->validated()),
            ]);

            $request->user()->forceFill(['onboarding_draft' => null])->save();
        });

        return redirect()->route('dashboard.index')->with('status', 'Your shop is live. Print your counter QR from Settings to start stamping.');
    }
}
