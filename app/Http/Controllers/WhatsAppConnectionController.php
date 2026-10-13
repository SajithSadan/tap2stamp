<?php

namespace App\Http\Controllers;

use App\Services\WhatsAppConnect;
use App\Services\WhatsAppConnectException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Owner dashboard → WhatsApp: the owner connects the shop's own WhatsApp
 * Business number - Meta's Embedded Signup popup, or the keys from their own
 * Meta app - or removes it. Always "my shop". Owner only: an admin viewing as
 * the owner can't (ViewAsOwner::OWNER_ONLY); the admin can only remove.
 */
class WhatsAppConnectionController extends Controller
{
    public function store(Request $request, WhatsAppConnect $connect): RedirectResponse
    {
        $input = $request->validate([
            'mode' => ['required', Rule::in(WhatsAppConnect::MODES)],
            'code' => ['required', 'string', 'max:2000'],
            'waba_id' => ['required', 'string', 'regex:/^\d{5,30}$/'],
            'phone_number_id' => ['required', 'string', 'regex:/^\d{5,30}$/'],
        ]);

        try {
            $account = $connect->connectWithCode(
                $request->user()->shop, $request->user(),
                $input['mode'], $input['code'], $input['waba_id'], $input['phone_number_id'],
            );
        } catch (WhatsAppConnectException $e) {
            throw ValidationException::withMessages(['whatsapp' => $e->getMessage()]);
        }

        return back()->with('status', 'WhatsApp connected: '.($account->display_phone_number ?? 'your number').'.');
    }

    /** The owner's own keys from Meta (their app's API Setup + a permanent token), checked with Meta first. */
    public function keys(Request $request, WhatsAppConnect $connect): RedirectResponse
    {
        $input = $request->validate([
            'waba_id' => ['required', 'string', 'regex:/^\d{5,30}$/'],
            'phone_number_id' => ['required', 'string', 'regex:/^\d{5,30}$/'],
            'access_token' => ['required', 'string', 'min:20', 'max:2000'],
        ], [
            'waba_id.regex' => 'The WhatsApp Business Account ID is a long number.',
            'phone_number_id.regex' => 'The phone number ID is a long number (not the phone number itself).',
        ]);

        try {
            $account = $connect->connectWithKeys(
                $request->user()->shop, $request->user(),
                $input['waba_id'], $input['phone_number_id'], trim($input['access_token']),
            );
        } catch (WhatsAppConnectException $e) {
            throw ValidationException::withMessages(['access_token' => $e->getMessage()]);
        }

        return back()->with('status', 'WhatsApp connected: '.($account->display_phone_number ?? 'your number').'.');
    }

    public function destroy(Request $request, WhatsAppConnect $connect): RedirectResponse
    {
        $connect->disconnect($request->user()->shop);

        return back()->with('status', 'WhatsApp number removed. You can connect another one.');
    }
}
