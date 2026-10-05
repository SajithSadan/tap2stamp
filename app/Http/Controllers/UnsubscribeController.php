<?php

namespace App\Http\Controllers;

use App\Models\CustomerShopCard;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The "Unsubscribe" button on every WhatsApp offer: /u/{token}. Opening the
 * link only shows the page (link previews fetch it too); the button on it
 * withdraws consent for that one shop.
 */
class UnsubscribeController extends Controller
{
    public function show(string $token): Response
    {
        $card = $this->card($token);

        return Inertia::render('Unsubscribe', [
            'shopName' => $card->shop->name,
            'subscribed' => $card->marketing_consent,
            'token' => $token,
            'theme' => $card->shop->appliedTheme(),
        ]);
    }

    public function store(string $token): RedirectResponse
    {
        $card = $this->card($token);

        if ($card->marketing_consent) {
            $card->update(['marketing_consent' => false, 'marketing_opted_out_at' => now()]);
        }

        return redirect()->route('marketing.unsubscribe', $token);
    }

    private function card(string $token): CustomerShopCard
    {
        return CustomerShopCard::with('shop')->where('marketing_unsubscribe_token', $token)->firstOrFail();
    }
}
