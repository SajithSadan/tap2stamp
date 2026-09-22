<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreShopOwnerRequest;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ShopOwnerController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Index', [
            'shops' => Shop::with('owner')->latest()->get()->map(fn (Shop $shop) => [
                'id' => $shop->id,
                'name' => $shop->name,
                'slug' => $shop->slug,
                'owner_name' => $shop->owner?->name,
                'owner_email' => $shop->owner?->email,
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Create');
    }

    public function store(StoreShopOwnerRequest $request): RedirectResponse
    {
        // Randomly generated rather than owner-chosen: there's no self-service
        // registration (see CLAUDE.md), so the admin hands this to the owner
        // out of band. Shown once via the flashed 'generatedPassword' prop.
        $password = Str::password(16);

        DB::transaction(function () use ($request, $password) {
            $owner = User::create([
                'name' => $request->string('owner_name')->value(),
                'email' => $request->string('owner_email')->value(),
                'password' => $password,
                'role' => UserRole::Owner,
            ]);

            Shop::create([
                'user_id' => $owner->id,
                'name' => $request->string('shop_name')->value(),
                'slug' => $request->string('shop_slug')->value(),
                'max_stamps' => $request->integer('shop_max_stamps'),
                'reward_title' => $request->string('shop_reward_title')->value(),
            ]);
        });

        return redirect()->route('admin.index')->with('generatedPassword', $password);
    }
}
