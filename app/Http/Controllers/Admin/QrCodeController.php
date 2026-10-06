<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateQrCodesRequest;
use App\Http\Requests\PrintQrCodesRequest;
use App\Http\Requests\RecordQrPrintRequest;
use App\Http\Requests\UpdateQrCodeRequest;
use App\Models\QrBatch;
use App\Models\QrCode;
use App\Models\QrDesign;
use App\Models\Shop;
use App\Services\ActivityLogger;
use App\Services\QrCodeGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class QrCodeController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim($request->string('search')->value());
        $batch = $request->integer('batch') ?: null;
        $status = in_array($request->query('status'), ['mapped', 'unmapped'], true) ? $request->query('status') : null;
        $shopId = $request->integer('shop') ?: null;
        $designId = $request->integer('design') ?: null;

        $codes = QrCode::with(['batch', 'shop', 'designs'])
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('code', 'like', "%{$search}%")
                ->orWhere('destination_url', 'like', "%{$search}%")))
            ->when($batch, fn ($q) => $q->where('qr_batch_id', $batch))
            ->when($shopId, fn ($q) => $q->where('shop_id', $shopId))
            ->when($designId, fn ($q) => $q->whereHas('designs', fn ($q) => $q->where('qr_designs.id', $designId)))
            ->when($status === 'mapped', fn ($q) => $q->whereNotNull('destination_url'))
            ->when($status === 'unmapped', fn ($q) => $q->whereNull('destination_url'))
            // Newest batch first, then in generation order - the same order
            // the codes come out on the printed sheets.
            ->orderByDesc('qr_batch_id')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (QrCode $qr) => [
                'id' => $qr->id,
                'code' => $qr->code,
                'scan_url' => $qr->scanUrl(),
                'destination_url' => $qr->destination_url,
                'shop_id' => $qr->shop_id,
                'shop_name' => $qr->shop?->name,
                'design_names' => $qr->designs->pluck('name')->values(),
                'batch_id' => $qr->qr_batch_id,
                'batch_label' => $qr->batch->label(),
                'serial' => $qr->serial,
                'mapped_at' => $qr->mapped_at?->diffForHumans(),
                'created_at' => $qr->created_at->format('j M Y'),
            ]);

        $batches = QrBatch::withCount(['codes', 'codes as mapped_count' => fn ($q) => $q->whereNotNull('destination_url')])
            ->latest('id')
            ->get();

        $shops = Shop::query()->withCount(['qrCodes', 'menuItems'])->orderBy('name')->get(['id', 'name', 'slug', 'menu_slug']);
        $designs = QrDesign::withCount('codes')->latest('id')->get()
            ->map(fn (QrDesign $design) => $design->toClient() + ['codes_count' => $design->codes_count]);

        $total = $batches->sum('codes_count');
        $mapped = $batches->sum('mapped_count');

        return Inertia::render('Admin/QrCodes/Index', [
            'codes' => $codes,
            'batches' => $batches->map(fn (QrBatch $b) => [
                'id' => $b->id,
                'label' => $b->label(),
                'codes_count' => $b->codes_count,
                'mapped_count' => $b->mapped_count,
                'created_at' => $b->created_at->format('j M Y'),
            ]),
            'shops' => $shops->map(fn (Shop $shop) => [
                'id' => $shop->id,
                'name' => $shop->name,
                'slug' => $shop->slug,
                'menu_slug' => $shop->menu_slug,
                'qr_codes_count' => $shop->qr_codes_count,
                'has_menu' => $shop->menu_items_count > 0,
            ]),
            'stats' => [
                'total' => $total,
                'mapped' => $mapped,
                'unmapped' => $total - $mapped,
                'batches' => $batches->count(),
            ],
            'filters' => [
                'search' => $search,
                'batch' => $batch,
                'status' => $status,
                'shop' => $shopId,
                'design' => $designId,
            ],
            'maxPerBatch' => QrCodeGenerator::MAX_PER_BATCH,
            // Sticker designs to print with (managed on the designs page).
            'designs' => $designs,
        ]);
    }

    public function store(GenerateQrCodesRequest $request, QrCodeGenerator $generator): RedirectResponse
    {
        $quantity = $request->integer('quantity');
        $batch = $generator->generate($quantity, $request->string('name')->trim()->value() ?: null);
        ActivityLogger::record('qr.generated', "Generated {$quantity} QR ".($quantity === 1 ? 'code' : 'codes')." in {$batch->label()}", null, $batch);

        return redirect()->route('admin.qr-codes.index', ['batch' => $batch->id])
            ->with('status', "Generated {$quantity} QR ".($quantity === 1 ? 'code' : 'codes')." in {$batch->label()}.");
    }

    public function update(UpdateQrCodeRequest $request, QrCode $qrCode): RedirectResponse
    {
        $url = $request->string('destination_url')->trim()->value() ?: null;

        // The code (and so the printed sticker) never changes - only where it points.
        $qrCode->update([
            'destination_url' => $url,
            'shop_id' => $this->shopIdForDestination($url, $request),
            'mapped_at' => $url ? now() : null,
        ]);

        return back()->with('status', $url ? "{$qrCode->code} now points to {$url}." : "{$qrCode->code} is unmapped.");
    }

    private function shopIdForDestination(?string $url, Request $request): ?int
    {
        if (! $url || strtolower((string) parse_url($url, PHP_URL_HOST)) !== strtolower($request->getHost())) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH) ?: '';

        // A shop's menu (/menu/{menu_slug}, or an old /menu/{id}) belongs to that shop too.
        if (preg_match('~^/menu/([a-z0-9-]+)/?$~', $path, $matches)) {
            return Shop::query()->where('menu_slug', $matches[1])->value('id')
                ?? (ctype_digit($matches[1]) ? Shop::query()->whereKey((int) $matches[1])->value('id') : null);
        }

        if (! preg_match('~^/s/([^/]+)/?$~', $path, $matches)) {
            return null;
        }

        return Shop::query()->where('slug', rawurldecode($matches[1]))->value('id');
    }

    /**
     * Deletes a whole batch and every code in it. Stickers already printed
     * from it stop working (they show "Nothing found"), so the page asks
     * for confirmation - and for typing DELETE when any code is mapped.
     */
    public function destroyBatch(QrBatch $qrBatch): RedirectResponse
    {
        $count = $qrBatch->codes()->count();
        $label = $qrBatch->label();

        $codes = $qrBatch->codes()->pluck('code')->all();

        DB::transaction(function () use ($qrBatch) {
            $qrBatch->codes()->delete();
            $qrBatch->delete();
        });

        ActivityLogger::record('qr.batch_deleted', "Deleted {$label} and its {$count} ".($count === 1 ? 'code' : 'codes'), null, $qrBatch, ['codes' => [$codes, null]]);

        return redirect()->route('admin.qr-codes.index')
            ->with('status', "Deleted {$label} and its {$count} ".($count === 1 ? 'code' : 'codes').'.');
    }

    /**
     * Deletes the chosen codes (one, or a selection). Printed stickers with
     * them stop working. Batches left with no codes go too. Serials are
     * stored, so the rest of a batch keeps the numbers on its stickers.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:'.QrCodeGenerator::MAX_PER_BATCH],
            'ids.*' => ['integer', 'distinct'],
        ])['ids'];

        $codes = QrCode::whereIn('id', $ids)->pluck('code')->all();

        [$deleted, $emptied] = DB::transaction(function () use ($ids) {
            $batchIds = QrCode::whereIn('id', $ids)->distinct()->pluck('qr_batch_id');
            $deleted = QrCode::whereIn('id', $ids)->delete();
            $emptied = QrBatch::whereIn('id', $batchIds)->doesntHave('codes')->get();
            QrBatch::whereKey($emptied->modelKeys())->delete();

            return [$deleted, $emptied];
        });

        $message = 'Deleted '.$deleted.' '.($deleted === 1 ? 'code' : 'codes');
        if ($emptied->isNotEmpty()) {
            $message .= ' and the now-empty '.$emptied->map->label()->join(', ', ' and ');
        }

        ActivityLogger::record('qr.deleted', $message, null, null, ['codes' => [$codes, null]]);

        return back()->with('status', $message.'.');
    }

    /** Codes + scan links for the client-side PDF (the server never builds the PDF itself). */
    public function printData(PrintQrCodesRequest $request): JsonResponse
    {
        $codes = QrCode::query()
            ->when($request->filled('batch'), fn ($q) => $q->where('qr_batch_id', $request->integer('batch')))
            ->when(! $request->filled('batch'), fn ($q) => $q->whereIn('qr_codes.id', $request->input('ids', [])))
            ->orderByDesc('qr_batch_id')
            ->orderBy('id')
            ->limit(QrCodeGenerator::MAX_PER_BATCH)
            ->get();

        return response()->json([
            'title' => $request->filled('batch')
                ? QrBatch::find($request->integer('batch'))->label()
                : 'Selected QR codes',
            'codes' => $codes->map(fn (QrCode $qr) => [
                'id' => $qr->id,
                'code' => $qr->code,
                'scan_url' => $qr->scanUrl(),
                // Its position in its batch, for designs that print a serial number.
                'serial' => $qr->serial,
            ]),
        ]);
    }

    public function recordPrint(RecordQrPrintRequest $request): JsonResponse
    {
        $designIds = $request->input('design_ids', []);
        $codes = QrCode::whereIn('id', $request->input('ids'))->get();

        if ($designIds !== []) {
            $now = now();
            $associations = $codes->flatMap(fn (QrCode $qrCode) => collect($designIds)->map(fn (int $designId) => [
                'qr_code_id' => $qrCode->id,
                'qr_design_id' => $designId,
                'created_at' => $now,
                'updated_at' => $now,
            ]));

            DB::table('qr_code_design')->insertOrIgnore($associations->all());
        }

        return response()->json(['recorded' => $codes->count()]);
    }
}
