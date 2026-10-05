<?php

namespace App\Http\Controllers;

use App\Http\Requests\StartCampaignRequest;
use App\Models\MarketingCampaign;
use App\Services\MarketingService;
use App\Services\WhatsAppGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Owner dashboard → WhatsApp: offers sent to this shop's opted-in customers.
 * Like every owner route, always "my shop" - never a shop in the URL.
 */
class MarketingController extends Controller
{
    public function index(Request $request, MarketingService $marketing, WhatsAppGateway $whatsApp): Response
    {
        $shop = $request->user()->shop;
        $campaigns = MarketingCampaign::where('shop_id', $shop->id)->latest('id');

        return Inertia::render('Dashboard/Marketing', [
            'shop' => DashboardController::shopSummary($shop),
            'audiences' => $marketing->audienceCounts($shop),
            'customersCount' => $shop->cards()->count(),
            'sentThisMonth' => (int) (clone $campaigns)
                ->where('created_at', '>=', now()->timezone('Europe/London')->startOfMonth())
                ->sum('sent_count'),
            'campaigns' => (clone $campaigns)->limit(20)->get()->map->summary(),
            'nextAllowedAt' => $marketing->nextAllowedAt($shop)?->timezone('Europe/London')->format('D j M, g:i A'),
            'whatsappReady' => $whatsApp->configured(),
            'templateBody' => WhatsAppGateway::TEMPLATE_BODY,
            'maxMessage' => MarketingService::MAX_MESSAGE,
            'activeDays' => MarketingService::ACTIVE_DAYS,
        ]);
    }

    public function store(StartCampaignRequest $request, MarketingService $marketing, WhatsAppGateway $whatsApp): RedirectResponse
    {
        $shop = $request->user()->shop;
        $audience = $request->string('audience')->value();

        $problem = match (true) {
            ! $whatsApp->configured() => "WhatsApp sending isn't switched on yet.",
            $marketing->nextAllowedAt($shop) !== null => 'You can send one message a day. Try again after '
                .$marketing->nextAllowedAt($shop)->timezone('Europe/London')->format('D j M, g:i A').'.',
            $marketing->audience($shop, $audience)->doesntExist() => 'Nobody in that group has opted in yet.',
            default => null,
        };

        if ($problem) {
            throw ValidationException::withMessages(['message' => $problem]);
        }

        // On the "uploads" disk (public/uploads, no storage:link on Hostinger), random name.
        $posterPath = $request->file('poster')?->store("marketing/{$shop->id}", 'uploads');

        $marketing->start($shop, $request->user(), $audience, $request->string('message')->value(), $posterPath ?: null);

        // The page sees the unfinished campaign and sends it in batches.
        return back();
    }

    /** One batch of an unfinished campaign; the page calls this until done. */
    public function send(Request $request, MarketingCampaign $campaign, MarketingService $marketing): JsonResponse
    {
        abort_unless($campaign->shop_id === $request->user()->shop->id, 404);

        if (! $campaign->isSending()) {
            return response()->json(['sent' => $campaign->sent_count, 'failed' => $campaign->failed_count, 'total' => $campaign->recipients_count, 'done' => true]);
        }

        @set_time_limit(120);

        return response()->json($marketing->sendBatch($campaign));
    }
}
