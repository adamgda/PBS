<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\Request;
use App\Http\Response;

/**
 * Middleware monitorowania (dokumentacja 14.8).
 *
 * Loguje do stderr strukturalną linię JSON z czasem trwania żądania,
 * statusem, endpointem i user_id — metryki API dla Grafany / agregacji logów.
 */
final class MonitoringMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        $start = microtime(true);
        $response = $next($request);
        $duration = (microtime(true) - $start) * 1000;

        $userId = $request->attribute('user_id');

        $entry = [
            'ts' => gmdate('c'),
            'method' => $request->method(),
            'path' => $request->path(),
            'status' => $response->statusCode(),
            'duration_ms' => round($duration, 2),
            'user_id' => $userId,
            'ip' => $request->ip(),
        ];

        error_log('[PBS:metric] ' . json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $response;
    }
}
