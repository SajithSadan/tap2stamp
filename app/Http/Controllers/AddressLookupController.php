<?php

namespace App\Http\Controllers;

use App\Services\AddressFinder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Same-origin proxy for the "Find address" button on the shop address
 * forms, so the findaddress.io key stays on the server. Signed-in users
 * only and throttled - every uncached lookup costs a credit.
 */
class AddressLookupController extends Controller
{
    public function __invoke(Request $request, AddressFinder $finder): JsonResponse
    {
        abort_unless($finder->configured(), 404);

        $input = $request->validate([
            'postcode' => ['required', 'string', 'max:10', 'regex:/^\s*[A-Za-z]{1,2}\d[A-Za-z\d]?\s*\d[A-Za-z]{2}\s*$/'],
            'house' => ['required', 'string', 'max:60'],
        ], [
            'postcode.regex' => 'Enter a full UK postcode, e.g. LS1 4AP.',
            'house.required' => 'Enter the house or building number or name.',
        ]);

        $result = $finder->find($input['postcode'], $input['house']);

        return match ($result['status']) {
            'found', 'partial' => response()->json($result),
            'not_found' => response()->json(['status' => 'not_found', 'message' => "We couldn't find that address. Check the postcode, or type the address below."], 404),
            default => response()->json(['status' => 'unavailable', 'message' => "Address lookup isn't working right now - please type the address below."], 503),
        };
    }
}
