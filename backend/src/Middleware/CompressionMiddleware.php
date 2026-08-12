<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\Request;
use App\Http\Response;

/**
 * Middleware kompresji gzip (dokumentacja 14.3).
 *
 * Włącza kompresję odpowiedzi, gdy klient obsługuje gzip (Accept-Encoding)
 * i rozmiar odpowiedzi przekracza 1 KB. Kompresja wykonywana w Response::send().
 */
final class CompressionMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        $response = $next($request);

        $acceptEncoding = $request->header('Accept-Encoding');
        if ($acceptEncoding !== null && str_contains(strtolower($acceptEncoding), 'gzip')) {
            $response->enableCompression();
        }

        return $response;
    }
}
