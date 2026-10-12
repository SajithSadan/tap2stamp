<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\User;
use App\Services\WhatsAppContacts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The WhatsApp button reports what the admin opened: a chat, or the welcome message. */
class WhatsAppContactController extends Controller
{
    public function store(Request $request, WhatsAppContacts $contacts): JsonResponse
    {
        $input = $request->validate([
            'kind' => ['required', Rule::in(WhatsAppContacts::KINDS)],
            'shop_id' => ['nullable', 'integer', 'exists:shops,id', 'required_without:user_id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $shop = isset($input['shop_id']) ? Shop::find($input['shop_id']) : null;
        $owner = isset($input['user_id']) ? User::find($input['user_id']) : null;

        $contacts->record($input['kind'], $shop, $owner);

        $sentAt = ($owner ?? $shop?->owner)?->fresh()?->welcome_sent_at;

        return response()->json([
            'welcome_sent_label' => $sentAt?->timezone('Europe/London')->format('j M Y'),
        ]);
    }
}
