<?php

namespace App\Http\Controllers;

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
        if (app()->environment('production') && ! $request->secure()) {
            abort(403);
        }

        $expected = config('deploy.migrate_token');
        $given = $request->bearerToken();

        if (blank($expected) || blank($given) || ! hash_equals($expected, (string) $given)) {
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
}
