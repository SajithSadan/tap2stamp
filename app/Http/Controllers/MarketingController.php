<?php

namespace App\Http\Controllers;

use App\Http\Requests\StartCampaignRequest;
use App\Models\MarketingCampaign;
use App\Services\MarketingService;
use App\Services\WhatsAppConnect;
use App\Services\WhatsAppGateway;
use App\Services\WhatsAppInbox;
use App\Services\WhatsAppTemplates;
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
    public function index(Request $request, MarketingService $marketing, WhatsAppConnect $connect, WhatsAppTemplates $templates, WhatsAppInbox $inbox): Response
    {
        $shop = $request->user()->shop;
        $account = $shop->whatsappAccount;
        $campaigns = MarketingCampaign::where('shop_id', $shop->id)->latest('id');

        // Still waiting for Meta to approve our templates? Ask (at most every few minutes).
        if ($account) {
            $templates->refreshIfWaiting($account);
        }

        return Inertia::render('Dashboard/Marketing', [
            'shop' => DashboardController::shopSummary($shop),
            'audiences' => $marketing->audienceCounts($shop),
            'customersCount' => $shop->cards()->count(),
            'sentThisMonth' => (int) (clone $campaigns)
                ->where('created_at', '>=', now()->timezone('Europe/London')->startOfMonth())
                ->sum('sent_count'),
            'campaigns' => (clone $campaigns)
                ->withCount([
                    'messages as delivered_count' => fn ($q) => $q->whereNotNull('delivered_at'),
                    'messages as read_count' => fn ($q) => $q->whereNotNull('read_at'),
                ])
                ->limit(20)->get()->map->summary(),
            // Ready to send = connected and the offer template approved; posters need the second one.
            'templatesReady' => [
                'offer' => $account !== null && $templates->ready($account, false),
                'poster' => $account !== null && $templates->ready($account, true),
            ],
            'chats' => $inbox->conversations($shop),
            'unreadChats' => $inbox->unreadCount($shop),
            'replyLimit' => WhatsAppInbox::MAX_REPLY,
            'nextAllowedAt' => $marketing->nextAllowedAt($shop)?->timezone('Europe/London')->format('D j M, g:i A'),
            // Sending needs the shop's own number connected.
            'whatsappReady' => $account !== null,
            'whatsappAccount' => $account?->summary(),
            // Null = our Meta app isn't set up: owners can't connect yet (the admin can, with keys).
            'embeddedSignup' => $connect->embeddedSignupSettings(),
            'templateBody' => WhatsAppGateway::TEMPLATE_BODY,
            'maxMessage' => MarketingService::MAX_MESSAGE,
            'activeDays' => MarketingService::ACTIVE_DAYS,
        ]);
    }

    public function store(StartCampaignRequest $request, MarketingService $marketing, WhatsAppTemplates $templates): RedirectResponse
    {
        $shop = $request->user()->shop;
        $audience = $request->string('audience')->value();
        $account = $shop->whatsappAccount()->first(); // fresh: it may have just been removed
        $withPoster = $request->hasFile('poster');

        if ($account) {
            $templates->refreshIfWaiting($account);
        }

        $problem = match (true) {
            ! $account => 'Connect your WhatsApp number first.',
            ! $templates->ready($account, $withPoster) => $withPoster && $templates->ready($account, false)
                ? "Meta hasn't approved offers with a poster yet. Send it without the poster, or try again later."
                : "Meta hasn't approved your offer message yet. It usually takes a few minutes, sometimes up to a day.",
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
