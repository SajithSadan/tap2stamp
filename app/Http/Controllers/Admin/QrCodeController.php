<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateQrCodesRequest;
use App\Http\Requests\PrintQrCodesRequest;
use App\Http\Requests\UpdateQrCodeRequest;
use App\Models\QrBatch;
use App\Models\QrCode;
use App\Services\QrCodeGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QrCodeController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim($request->string('search')->value());
        $batch = $request->integer('batch') ?: null;
        $status = in_array($request->query('status'), ['mapped', 'unmapped'], true) ? $request->query('status') : null;

        $codes = QrCode::with('batch')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('code', 'like', "%{$search}%")
                ->orWhere('destination_url', 'like', "%{$search}%")))
            ->when($batch, fn ($q) => $q->where('qr_batch_id', $batch))
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
                'batch_id' => $qr->qr_batch_id,
                'batch_label' => $qr->batch->label(),
                'mapped_at' => $qr->mapped_at?->diffForHumans(),
                'created_at' => $qr->created_at->format('j M Y'),
            ]);

        $batches = QrBatch::withCount(['codes', 'codes as mapped_count' => fn ($q) => $q->whereNotNull('destination_url')])
            ->latest('id')
            ->get();

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
            ],
            'maxPerBatch' => QrCodeGenerator::MAX_PER_BATCH,
        ]);
    }

    public function store(GenerateQrCodesRequest $request, QrCodeGenerator $generator): RedirectResponse
    {
        $quantity = $request->integer('quantity');
        $batch = $generator->generate($quantity, $request->string('name')->trim()->value() ?: null);

        return redirect()->route('admin.qr-codes.index', ['batch' => $batch->id])
            ->with('status', "Generated {$quantity} QR ".($quantity === 1 ? 'code' : 'codes')." in {$batch->label()}.");
    }

    public function update(UpdateQrCodeRequest $request, QrCode $qrCode): RedirectResponse
    {
        $url = $request->string('destination_url')->trim()->value() ?: null;

        // The code (and so the printed sticker) never changes - only where it points.
        $qrCode->update([
            'destination_url' => $url,
            'mapped_at' => $url ? now() : null,
        ]);

        return back()->with('status', $url ? "{$qrCode->code} now points to {$url}." : "{$qrCode->code} is unmapped.");
    }

    /** Codes + scan links for the client-side PDF (the server never builds the PDF itself). */
    public function printData(PrintQrCodesRequest $request): JsonResponse
    {
        $codes = QrCode::query()
            ->when($request->filled('batch'), fn ($q) => $q->where('qr_batch_id', $request->integer('batch')))
            ->when(! $request->filled('batch'), fn ($q) => $q->whereIn('id', $request->input('ids', [])))
            ->orderByDesc('qr_batch_id')
            ->orderBy('id')
            ->limit(QrCodeGenerator::MAX_PER_BATCH)
            ->get();

        return response()->json([
            'title' => $request->filled('batch')
                ? QrBatch::find($request->integer('batch'))->label()
                : 'Selected QR codes',
            'codes' => $codes->map(fn (QrCode $qr) => [
                'code' => $qr->code,
                'scan_url' => $qr->scanUrl(),
            ]),
        ]);
    }
}
