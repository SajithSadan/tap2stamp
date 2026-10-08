<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveQrDesignRequest;
use App\Models\QrDesign;
use App\Support\QrStyle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sticker designs for QR printing: upload a background, place and style the
 * QR on it, save. Printing (lib/qrPrint.js) then renders each code's QR in
 * that spot and style. Images live on the `uploads` disk, like shop banners
 * (no storage:link on Hostinger).
 */
class QrDesignController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/QrCodes/Designs', [
            'designs' => QrDesign::latest('id')->get()->map->toClient(),
        ]);
    }

    public function create(): Response
    {
        return $this->editor(null);
    }

    public function edit(QrDesign $qrDesign): Response
    {
        return $this->editor($qrDesign);
    }

    public function store(SaveQrDesignRequest $request): RedirectResponse
    {
        [$width, $height] = $request->imageSize();

        $design = QrDesign::create([
            ...$request->safe()->only(['name', 'qr_x', 'qr_y', 'qr_size', 'width_mm', 'preset', 'height_mm']),
            'style' => $request->style(),
            // Random file names (hashName) - never the uploaded filename.
            'image_path' => $request->file('image')->store('qr-designs', 'uploads'),
            'image_width' => $width,
            'image_height' => $height,
            'logo_path' => $request->file('logo')?->store('qr-designs/logos', 'uploads'),
        ]);

        return redirect()->route('admin.qr-codes.designs.edit', $design)->with('status', "Saved “{$design->name}”. Pick it under “Print with” on the QR codes page.");
    }

    public function update(SaveQrDesignRequest $request, QrDesign $qrDesign): RedirectResponse
    {
        $attributes = [
            ...$request->safe()->only(['name', 'qr_x', 'qr_y', 'qr_size', 'width_mm', 'preset', 'height_mm']),
            'style' => $request->style(),
        ];
        $replaced = [];

        if ($request->hasFile('image')) {
            [$width, $height] = $request->imageSize();
            $replaced[] = $qrDesign->image_path;
            $attributes += [
                'image_path' => $request->file('image')->store('qr-designs', 'uploads'),
                'image_width' => $width,
                'image_height' => $height,
            ];
        }

        if ($request->hasFile('logo') || $request->boolean('remove_logo')) {
            $replaced[] = $qrDesign->logo_path;
            $attributes['logo_path'] = $request->file('logo')?->store('qr-designs/logos', 'uploads');
        }

        $qrDesign->update($attributes);
        Storage::disk('uploads')->delete(array_filter($replaced));

        return redirect()->route('admin.qr-codes.designs.edit', $qrDesign)->with('status', "Saved “{$qrDesign->name}”.");
    }

    /**
     * Make this the default design ({default: true}) - the one overseas owners
     * download their QR in unless their shop has its own - or stop it being
     * the default. At most one at a time.
     */
    public function setDefault(Request $request, QrDesign $qrDesign): RedirectResponse
    {
        $default = $request->validate(['default' => ['required', 'boolean']])['default'];

        DB::transaction(function () use ($qrDesign, $default) {
            if ($default) {
                QrDesign::whereKeyNot($qrDesign->id)->where('is_default', true)->update(['is_default' => false]);
            }
            $qrDesign->forceFill(['is_default' => (bool) $default])->save();
        });

        return back()->with('status', $default
            ? "“{$qrDesign->name}” is now the default design."
            : "“{$qrDesign->name}” is no longer the default - owners without a design of their own get a plain QR.");
    }

    public function destroy(QrDesign $qrDesign): RedirectResponse
    {
        Storage::disk('uploads')->delete(array_filter([$qrDesign->image_path, $qrDesign->logo_path]));
        $qrDesign->delete();

        return redirect()->route('admin.qr-codes.designs.index')->with('status', "Deleted “{$qrDesign->name}”. Already-printed stickers aren't affected.");
    }

    private function editor(?QrDesign $design): Response
    {
        return Inertia::render('Admin/QrCodes/DesignEditor', [
            'design' => $design?->toClient(),
            'defaults' => QrStyle::DEFAULTS,
            'presets' => QrDesign::PRESETS,
            'limits' => [
                'min_height_mm' => QrDesign::MIN_HEIGHT_MM,
                'max_height_mm' => QrDesign::MAX_HEIGHT_MM,
                'min_width_mm' => QrDesign::MIN_WIDTH_MM,
                'max_width_mm' => QrDesign::MAX_WIDTH_MM,
                'min_qr_mm' => SaveQrDesignRequest::MIN_QR_MM,
            ],
        ]);
    }
}
