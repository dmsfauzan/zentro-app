<?php

namespace App\Support\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Observability scaffold: catat request lambat dan error server (5xx) dengan
 * konteks minimal yang bisa dikorelasikan lewat `request_id`.
 *
 * Tidak memakai metrik eksternal (prometheus) agar tetap tanpa dependensi baru;
 * cukup log terstruktur yang siap dikirim ke agregator.
 */
class LogRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);

        $response = $next($request);

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $status = $response->getStatusCode();
        $threshold = (int) config('zentro.observability.slow_request_ms', 1000);

        $userId = null;

        try {
            $userId = optional($request->user())->getKey();
        } catch (\Throwable) {
            // Resolusi user tidak boleh menggagalkan logging.
            $userId = null;
        }

        $context = [
            'method' => $request->method(),
            'path' => '/'.ltrim($request->path(), '/'),
            'status' => $status,
            'duration_ms' => $durationMs,
            'request_id' => $request->attributes->get(AssignRequestId::ATTRIBUTE),
            'user_id' => $userId,
        ];

        if ($status >= 500) {
            Log::channel(config('zentro.observability.log_channel', 'stack'))
                ->error('http.server_error', $context);
        } elseif ($durationMs >= $threshold) {
            Log::channel(config('zentro.observability.log_channel', 'stack'))
                ->warning('http.slow_request', $context);
        }

        return $response;
    }
}
