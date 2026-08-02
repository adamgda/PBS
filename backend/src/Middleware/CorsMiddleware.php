<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\Request;
use App\Http\Response;

/**
 * Middleware CORS — whitelist dozwolonych originów z konfiguracji.
 */
final class CorsMiddleware implements MiddlewareInterface
{
    /** @var array<int, string> */
    private readonly array $allowedOrigins;

    /**
     * @param array<int, string> $allowedOrigins
     */
    public function __construct(array $allowedOrigins)
    {
        $this->allowedOrigins = $allowedOrigins;
    }

    public function process(Request $request, callable $next): Response
    {
        $origin = $request->header('Origin');

        if ($origin !== null && in_array($origin, $this->allowedOrigins, true)) {
            $response = $next($request);
            $response->header('Access-Control-Allow-Origin', $origin);
            $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
            $response->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            $response->header('Access-Control-Allow-Credentials', 'true');
            $response->header('Access-Control-Max-Age', '86400');

            return $response;
        }

        if ($request->method() === 'OPTIONS') {
            return Response::json(204, []);
        }

        return $next($request);
    }
}
