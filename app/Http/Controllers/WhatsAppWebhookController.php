<?php

namespace App\Http\Controllers;

use App\Services\WhatsAppWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Meta's WhatsApp webhook for our Meta app (Meta dashboard → WhatsApp →
 * Configuration: callback URL /api/whatsapp/webhook, verify token
 * WHATSAPP_WEBHOOK_VERIFY_TOKEN, fields "messages" and
 * "message_template_status_update").
 */
class WhatsAppWebhookController extends Controller
{
    /** Meta's one-off check when the callback URL is saved. */
    public function verify(Request $request): Response
    {
        $token = (string) config('services.whatsapp.webhook_verify_token');

        if ($token !== '' && $request->query('hub_mode') === 'subscribe'
            && hash_equals($token, (string) $request->query('hub_verify_token'))) {
            return response((string) $request->query('hub_challenge'), 200, ['Content-Type' => 'text/plain']);
        }

        return response('Forbidden', 403);
    }

    public function receive(Request $request, WhatsAppWebhook $webhook): JsonResponse
    {
        if (! $webhook->verifySignature($request->getContent(), $request->header('X-Hub-Signature-256'))) {
            Log::warning('Rejected WhatsApp webhook', ['ip' => $request->ip()]);

            return response()->json(['error' => 'Invalid signature.'], 401);
        }

        $webhook->handle($request->json()->all());

        return response()->json(['received' => true]);
    }
}
