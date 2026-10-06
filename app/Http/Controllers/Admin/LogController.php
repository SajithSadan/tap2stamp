<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Admin → Logs: the end of storage/logs/laravel.log, for checking errors on
 * hosting without SSH. Admin only - the log can hold sensitive details.
 */
class LogController extends Controller
{
    public const LINE_OPTIONS = [100, 200, 500, 1000, 2000];

    public function index(): Response
    {
        return Inertia::render('Admin/Logs', ['lineOptions' => self::LINE_OPTIONS]);
    }

    /** The last N lines, read backwards so a big log is never loaded whole. */
    public function lines(Request $request): JsonResponse
    {
        $validated = $request->validate(['lines' => ['nullable', 'integer', 'min:10', 'max:'.max(self::LINE_OPTIONS)]]);
        $count = (int) ($validated['lines'] ?? 200);
        $path = self::path();

        if (! is_file($path)) {
            return response()->json(['lines' => [], 'size' => 0, 'exists' => false]);
        }

        return response()->json(['lines' => self::tail($path, $count), 'size' => filesize($path), 'exists' => true]);
    }

    public function download(): BinaryFileResponse
    {
        abort_unless(is_file(self::path()), 404);

        return response()->download(self::path(), 'laravel-'.now()->format('Y-m-d-His').'.log');
    }

    public function clear(Request $request): JsonResponse
    {
        if (is_file(self::path())) {
            file_put_contents(self::path(), '');
        }

        ActivityLogger::record('logs.cleared', 'Cleared the Laravel log');

        // The first line of the fresh log says who emptied it.
        Log::info('Log cleared by admin', ['admin_id' => $request->user()->id]);

        return response()->json(['cleared' => true]);
    }

    /** The `single` channel's file (LOG_STACK=single) - storage/logs/laravel.log. */
    private static function path(): string
    {
        return config('logging.channels.single.path', storage_path('logs/laravel.log'));
    }

    /** @return list<string> */
    private static function tail(string $path, int $count): array
    {
        $handle = fopen($path, 'rb');
        if (! $handle) {
            return [];
        }

        fseek($handle, 0, SEEK_END);
        $position = ftell($handle);
        $buffer = '';

        // Read 16 KB blocks from the end until there are enough line breaks.
        while ($position > 0 && substr_count($buffer, "\n") <= $count) {
            $read = min(16384, $position);
            $position -= $read;
            fseek($handle, $position);
            $buffer = fread($handle, $read).$buffer;
        }
        fclose($handle);

        $lines = preg_split('/\r?\n/', rtrim($buffer, "\r\n"));

        return array_values(array_slice($lines, -$count));
    }
}
