<?php

namespace App\Http\Controllers;

use App\Services\StampService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OwnerScanController extends Controller
{
    public function __invoke(Request $request, StampService $service): JsonResponse
    {
        // redeem: the "Mark reward as given" confirmation for a full card (StampService::scan).
        $request->validate(['payload' => ['required', 'string'], 'redeem' => ['sometimes', 'boolean']]);
        $owner = $request->user();

        [$status, $body] = $service->scan(
            $owner->shop,
            $request->string('payload')->value(),
            ownerId: $owner->id,
            redeem: $request->boolean('redeem'),
        );

        return response()->json($body, $status);
    }
}
