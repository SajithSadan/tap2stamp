<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Lets `php artisan migrate` be triggered over HTTP after a production
 * deploy, for hosting plans (e.g. Hostinger's cheaper tiers) that don't
 * give SSH/terminal access.
 *
 * Protected by a bearer token (DEPLOY_MIGRATE_TOKEN) checked in constant
 * time; refuses to run if no token is configured (fail closed, not open).
 * Deliberately runs only `migrate --force` — never `--seed` or `:fresh`,
 * both of which could destroy real production data if this URL leaked.
 */
class DeployController extends Controller
{
    public function migrate(Request $request): JsonResponse
    {
        if (! $this->authorized($request)) {
            Log::warning('Rejected deploy migrate attempt', ['ip' => $request->ip()]);

            abort(403);
        }

        try {
            Artisan::call('migrate', ['--force' => true]);
        } catch (Throwable $e) {
            Log::error('Deploy migration failed', ['message' => $e->getMessage()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Migration failed. Check the server logs.',
            ], 500);
        }

        Log::info('Deploy migration run via HTTP', ['ip' => $request->ip()]);

        return response()->json([
            'status' => 'ok',
            'output' => Artisan::output(),
        ]);
    }

    /**
     * Bootstraps the first admin account on a host with no SSH access (the
     * same problem `migrate()` above solves for schema changes — see
     * CLAUDE.md "Admin panel"). Idempotent: safe to call on every deploy,
     * never overwrites an existing admin's password. Shares the same bearer
     * token as migrate() - both are trusted-deployer-only operations.
     */
    public function seedAdmin(Request $request): JsonResponse
    {
        if (! $this->authorized($request)) {
            Log::warning('Rejected deploy seed-admin attempt', ['ip' => $request->ip()]);

            abort(403);
        }

        $email = config('deploy.admin_email');
        $password = config('deploy.admin_password');

        if (blank($email) || blank($password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'ADMIN_EMAIL / ADMIN_PASSWORD are not configured.',
            ], 422);
        }

        $admin = User::firstOrCreate(
            ['email' => $email],
            ['name' => 'Admin', 'password' => $password, 'role' => UserRole::Admin]
        );

        Log::info('Deploy seed-admin run via HTTP', ['ip' => $request->ip(), 'created' => $admin->wasRecentlyCreated]);

        return response()->json(['status' => 'ok', 'created' => $admin->wasRecentlyCreated]);
    }

    private function authorized(Request $request): bool
    {
        if (app()->environment('production') && ! $request->secure()) {
            return false;
        }

        $expected = config('deploy.migrate_token');
        $given = $request->bearerToken();

        return ! blank($expected) && ! blank($given) && hash_equals($expected, (string) $given);
    }
}
