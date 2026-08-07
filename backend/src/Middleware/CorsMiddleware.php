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
        $isAllowed = $origin !== null && in_array($origin, $this->allowedOrigins, true);

        // Preflight (OPTIONS) — krótka odpowiedź z nagłówkami CORS, bez wywoływania
        // routera i pozostałego pipeline'u. Preflight nigdy nie powinien trafiać
        // do warstwy biznesowej (router zwróciłby 405, a ewentualny wyjątek
        // ominąłby dodawanie nagłówków CORS → błąd w przeglądarce).
        if ($request->method() === 'OPTIONS') {
            $response = Response::noContent();
            if ($isAllowed && $origin !== null) {
                $this->addCorsHeaders($response, $origin);
            }
            return $response;
        }

        // Zwykłe żądanie — przepuść przez pipeline i dodaj nagłówki CORS.
        $response = $next($request);
        if ($isAllowed && $origin !== null) {
            $this->addCorsHeaders($response, $origin);
        }

        return $response;
    }

    /**
     * Dodaje zestaw nagłówków CORS do odpowiedzi dla dozwolonego originu.
     */
    private function addCorsHeaders(Response $response, string $origin): void
    {
        $response->header('Access-Control-Allow-Origin', $origin);
        $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        $response->header('Access-Control-Allow-Credentials', 'true');
        $response->header('Access-Control-Max-Age', '86400');
    }
}
