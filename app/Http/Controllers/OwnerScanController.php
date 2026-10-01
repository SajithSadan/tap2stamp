<?php

namespace App\Http\Controllers;

use App\Services\StampService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OwnerScanController extends Controller
{
    public function __invoke(Request $request, StampService $service): JsonResponse
    {
        $request->validate(['payload' => ['required', 'string']]);
        $owner = $request->user();

        [$status, $body] = $service->scan(
            $owner->shop,
            $request->string('payload')->value(),
            ownerId: $owner->id,
        );

        return response()->json($body, $status);
    }
}
