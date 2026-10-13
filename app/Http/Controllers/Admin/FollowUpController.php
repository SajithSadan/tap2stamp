<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\ShopTask;
use App\Services\CrmAssistant;
use App\Support\CrmStage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin → Follow-ups: what to do today. Overdue / today's follow-ups, trials
 * ending within a week, the rest of the open follow-ups, and the AI weekly
 * briefing (which shops need the admin, why, and one action each).
 */
class FollowUpController extends Controller
{
    public function index(CrmAssistant $assistant): Response
    {
        $today = now('Europe/London')->startOfDay();
        $tasks = ShopTask::open()
            ->with('shop:id,name,contact_name,contact_phone_code,contact_phone,user_id')
            ->orderByRaw('due_on IS NULL, due_on')
            ->orderBy('id')
            ->get();
        $row = fn (ShopTask $task) => [
            ...$task->toRow(),
            'shop_name' => $task->shop->name,
            'whatsapp' => $task->shop->whatsappNumber(),
            'contact_name' => $task->shop->contact_name,
        ];

        $trials = Shop::whereNull('blocked_at')
            ->whereNotIn('crm_stage', [CrmStage::PAID, CrmStage::LOST])
            ->whereBetween('trial_ends_at', [$today->copy()->subDays(3), $today->copy()->addDays(8)])
            ->orderBy('trial_ends_at')
            ->get(['id', 'name', 'trial_ends_at', 'crm_stage', 'contact_name', 'contact_phone_code', 'contact_phone', 'country'])
            ->map(fn (Shop $shop) => [
                'id' => $shop->id,
                'name' => $shop->name,
                'trial_ends_label' => $shop->trial_ends_at->timezone('Europe/London')->format('D j M'),
                'trial_days_left' => (int) $today->diffInDays($shop->trial_ends_at->copy()->timezone('Europe/London')->startOfDay(), false),
                'stage' => $shop->crm_stage,
                'whatsapp' => $shop->whatsappNumber(),
                'contact_name' => $shop->contact_name,
            ]);

        return Inertia::render('Admin/FollowUps', [
            'today' => $tasks->filter(fn ($t) => $t->due_on && $t->due_on->lte($today))->map($row)->values(),
            'upcoming' => $tasks->reject(fn ($t) => $t->due_on && $t->due_on->lte($today))->map($row)->values(),
            'trials' => $trials,
            'briefing' => CrmAssistant::lastBriefing(),
            'flags' => CrmAssistant::FLAGS,
            'aiEnabled' => $assistant->enabled(),
            // How many shops the rules would send to the next briefing.
            'candidates' => fn () => $assistant->candidates()->count(),
        ]);
    }

    public function briefing(Request $request, CrmAssistant $assistant): RedirectResponse
    {
        abort_unless($assistant->enabled(), 404);

        $result = $assistant->weeklyBriefing($request->user());

        if (! is_array($result)) {
            throw ValidationException::withMessages(['briefing' => match ($result) {
                'empty' => 'No shop needs a follow-up right now - nothing to brief.',
                'busy' => 'The AI is busy - try again in a minute.',
                default => "Couldn't make the briefing right now.",
            }]);
        }

        return back()->with('status', 'Briefing ready.');
    }
}
