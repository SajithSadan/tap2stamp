<?php

namespace App\Http\Controllers;

use App\Models\QrCode;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class QrRedirectController extends Controller
{
    /**
     * Where a scanned sticker lands. The printed QR only ever holds this
     * permanent /qr/{code} link, so the admin can change the destination
     * at any time without reprinting anything.
     */
    public function show(Request $request, string $code): Response
    {
        $qrCode = QrCode::where('code', strtoupper($code))->first();

        // Scan-to-map: printed stickers carry no readable label, so the admin
        // identifies one by scanning it while logged in and maps it right here.
        if ($qrCode && $request->user()?->isAdmin()) {
            return Inertia::render('Admin/QrCodes/Scan', [
                'qr' => [
                    'id' => $qrCode->id,
                    'code' => $qrCode->code,
                    'scan_url' => $qrCode->scanUrl(),
                    'destination_url' => $qrCode->destination_url,
                    'batch_label' => $qrCode->batch->label(),
                    'mapped_at' => $qrCode->mapped_at?->diffForHumans(),
                ],
            ])->toResponse($request);
        }

        if ($qrCode?->destination_url) {
            // 302, never 301: browsers cache a 301 indefinitely, which would
            // keep sending the sticker to its old destination after a remap.
            return redirect()->away($qrCode->destination_url)
                ->header('Cache-Control', 'no-store');
        }

        // Unknown and not-yet-mapped codes both end here. Only a real code is
        // echoed back (so someone can quote it to the shop), never raw input.
        return Inertia::render('Qr/NotFound', ['code' => $qrCode?->code])
            ->toResponse($request)
            ->setStatusCode(404);
    }
}
