<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\ShopNote;
use App\Models\ShopTask;
use App\Services\CrmAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The admin CRM on one shop (Account tab): notes, tags, follow-up tasks and
 * the AI "Brief me". Notes / tasks are looked up through the shop in the URL
 * (scoped bindings), so one shop's id can't reach another shop's records.
 */
class ShopCrmController extends Controller
{
    public function storeNote(Request $request, Shop $shop): RedirectResponse
    {
        $input = $request->validate(['body' => ['required', 'string', 'max:'.ShopNote::MAX_LENGTH]]);

        $shop->notes()->create(['body' => trim($input['body']), 'user_id' => $request->user()->id]);

        return back()->with('status', 'Note added.');
    }

    public function destroyNote(Shop $shop, ShopNote $note): RedirectResponse
    {
        $note->delete();

        return back()->with('status', 'Note deleted.');
    }

    public function updateTags(Request $request, Shop $shop): RedirectResponse
    {
        $input = $request->validate([
            'tags' => ['present', 'array', 'max:'.Shop::MAX_CRM_TAGS],
            'tags.*' => ['nullable', 'string', 'max:30'],
        ]);

        $tags = Shop::tidyCrmTags($input['tags']);
        $shop->forceFill(['crm_tags' => $tags ?: null])->save();

        return back();
    }

    public function storeTask(Request $request, Shop $shop): RedirectResponse
    {
        $input = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'due_on' => ['nullable', 'date'],
        ]);

        $shop->tasks()->create([
            'title' => trim($input['title']),
            'due_on' => $input['due_on'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Follow-up added.');
    }

    /** Tick off (or reopen) a follow-up. */
    public function updateTask(Request $request, Shop $shop, ShopTask $task): RedirectResponse
    {
        $done = $request->validate(['done' => ['required', 'boolean']])['done'];

        $task->forceFill([
            'done_at' => $done ? now() : null,
            'done_by' => $done ? $request->user()->id : null,
        ])->save();

        return back();
    }

    public function destroyTask(Shop $shop, ShopTask $task): RedirectResponse
    {
        $task->delete();

        return back()->with('status', 'Follow-up deleted.');
    }

    /** "Brief me" before a call (Gemini, not stored). */
    public function brief(Shop $shop, CrmAssistant $assistant): JsonResponse
    {
        abort_unless($assistant->enabled(), 404);

        $prep = $assistant->callPrep($shop);

        return is_array($prep)
            ? response()->json($prep)
            : response()->json(['message' => $prep === 'busy' ? 'The AI is busy - try again in a minute.' : "Couldn't prepare the brief right now."], 503);
    }
}
