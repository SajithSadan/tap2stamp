<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Shop;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin → Activity: the audit log of what admins, owners and staff did
 * (App\Services\ActivityLogger), in the data grid. The period / shop / role
 * filters run here; search, sort, columns and CSV run in the grid on the
 * rows sent (newest MAX_ROWS of the period).
 */
class ActivityController extends Controller
{
    public const RANGES = [1, 7, 30, 90];

    public const MAX_ROWS = 2000;

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'days' => ['nullable', 'integer', Rule::in(self::RANGES)],
            'shop' => ['nullable', 'integer', 'exists:shops,id'],
            'role' => ['nullable', Rule::in(ActivityLogger::ACTOR_TYPES)],
        ]);
        $days = (int) ($filters['days'] ?? 7);

        $query = ActivityLog::query()
            ->with('shop:id,name')
            ->where('created_at', '>=', now()->subDays($days))
            ->when($filters['shop'] ?? null, fn ($q, $shop) => $q->where('shop_id', $shop))
            ->when($filters['role'] ?? null, fn ($q, $role) => $q->where('actor_type', $role));

        $total = (clone $query)->count();

        return Inertia::render('Admin/Activity', [
            'rows' => $query->latest('created_at')->latest('id')->limit(self::MAX_ROWS)->get()
                ->map(fn (ActivityLog $log) => [
                    'id' => $log->id,
                    'at' => $log->created_at->toIso8601String(),
                    'actor_type' => $log->actor_type,
                    'actor_name' => $log->actor_name,
                    'as_owner' => $log->as_owner,
                    'shop' => $log->shop ? ['id' => $log->shop->id, 'name' => $log->shop->name] : null,
                    'action' => $log->action,
                    'description' => $log->description,
                    'changes' => $log->changes,
                ]),
            'total' => $total,
            'maxRows' => self::MAX_ROWS,
            'filters' => ['days' => $days, 'shop' => $filters['shop'] ?? null, 'role' => $filters['role'] ?? null],
            'ranges' => self::RANGES,
            'shops' => Shop::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
