<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\Request;
use App\Http\Response;

/**
 * Pipeline middleware — buduje łańcuch middleware i wywołuje je w kolejności.
 */
final class MiddlewarePipeline
{
    /** @var array<int, MiddlewareInterface> */
    private readonly array $middleware;

    /**
     * @param array<int, MiddlewareInterface> $middleware
     */
    public function __construct(array $middleware)
    {
        $this->middleware = $middleware;
    }

    public function handle(Request $request, callable $finalHandler): Response
    {
        return $this->createChain(0, $request, $finalHandler);
    }

    private function createChain(int $index, Request $request, callable $finalHandler): Response
    {
        if (!isset($this->middleware[$index])) {
            return $finalHandler($request);
        }

        $middleware = $this->middleware[$index];
        $next = fn (Request $req): Response => $this->createChain($index + 1, $req, $finalHandler);

        return $middleware->process($request, $next);
    }
}