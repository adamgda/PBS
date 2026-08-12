<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\Request;
use App\Http\Response;

/**
 * Middleware nagłówków cache HTTP (dokumentacja 14.2).
 *
 * Zasady:
 *  - GET (bez mutacji) → `Cache-Control: private, max-age=300` (5 min) + ETag
 *  - Mutacje (POST/PUT/PATCH/DELETE) → `Cache-Control: no-store` (nadpisuje)
 *
 * Dane osobowe są oznaczane jako `private` — nigdy nie cache'owane współdzielenie.
 */
final class CacheControlMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        $response = $next($request);

        $method = $request->method();

        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $response->header('Cache-Control', 'no-store');
            $response->header('Pragma', 'no-cache');

            return $response;
        }

        // GET: prywatny cache 5 min + ETag oparty o hash treści.
        $response->header('Cache-Control', 'private, max-age=300');
        $body = json_encode($response->data(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $etag = '"' . hash('sha256', $body === false ? '' : $body) . '"';
        $response->header('ETag', $etag);

        return $response;
    }
}
