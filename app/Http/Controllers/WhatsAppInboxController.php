<?php

namespace App\Http\Controllers;

use App\Services\WhatsAppInbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Owner dashboard → WhatsApp → Replies: one chat at a time (JSON for the
 * page), always within "my shop". Replying is owner only
 * (ViewAsOwner::OWNER_ONLY); an admin viewing as the owner can read a chat
 * but doesn't mark it as seen.
 */
class WhatsAppInboxController extends Controller
{
    public function show(Request $request, WhatsAppInbox $inbox): JsonResponse
    {
        $shop = $request->user()->shop;
        $phone = $this->phone($request);

        if (! $request->attributes->get('viewAsAdmin')) {
            $inbox->markSeen($shop, $phone);
        }

        return response()->json($inbox->thread($shop, $phone));
    }

    public function reply(Request $request, WhatsAppInbox $inbox): JsonResponse
    {
        $request->validate(['body' => ['required', 'string', 'max:'.WhatsAppInbox::MAX_REPLY]]);
        $shop = $request->user()->shop;
        $phone = $this->phone($request);

        $inbox->reply($shop, $request->user(), $phone, trim($request->string('body')->value()));

        return response()->json($inbox->thread($shop, $phone));
    }

    private function phone(Request $request): string
    {
        $phone = WhatsAppInbox::normalisePhone($request->input('phone'));

        if (! $phone) {
            throw ValidationException::withMessages(['phone' => 'Pick a chat.']);
        }

        return $phone;
    }
}
