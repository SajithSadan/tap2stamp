<?php

namespace App\Http\Controllers;

use App\Services\StaffAppLinks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The staff app on the home screen (see StaffAppLinks): the one-time code's
 * swap for the device token, and the install manifest whose start address
 * carries the code (iPhones may open the manifest's start_url, not the page
 * they added).
 */
class StaffAppController extends Controller
{
    /** Signed-in staff device → a one-time link for adding the app to the home screen. */
    public function link(Request $request, StaffAppLinks $links): JsonResponse
    {
        $code = $links->issue($request->attributes->get('staffDevice'), (string) $request->bearerToken());

        return response()->json([
            'code' => $code,
            'url' => '/staff?link='.$code,
            'expires_in_hours' => StaffAppLinks::LIFETIME_HOURS,
        ]);
    }

    /** The installed app's first launch: the code → the device token (once). */
    public function redeem(Request $request, StaffAppLinks $links): JsonResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:64']]);

        $token = $links->redeem($request->string('code')->value());

        return $token
            ? response()->json(['token' => $token])
            : response()->json(['message' => 'This link has expired or was already used.', 'code' => 'link_expired'], 404);
    }

    /** public/manifest.webmanifest, starting at /staff?link={code}. */
    public function manifest(string $code): JsonResponse
    {
        $manifest = json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true);
        $manifest['start_url'] = '/staff?link='.$code;

        return response()->json($manifest, 200, ['Content-Type' => 'application/manifest+json']);
    }
}
