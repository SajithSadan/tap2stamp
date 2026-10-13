<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Services\WhatsAppConnect;
use Illuminate\Http\RedirectResponse;

/**
 * Admin → shop settings → WhatsApp: see the shop's number and remove it
 * (e.g. a wrong number). Only the owner connects one - they're the one who
 * fills in Meta's forms (WhatsAppConnectionController).
 */
class ShopWhatsAppController extends Controller
{
    public function destroy(Shop $shop, WhatsAppConnect $connect): RedirectResponse
    {
        $connect->disconnect($shop);

        return back()->with('status', 'WhatsApp number removed.');
    }
}
