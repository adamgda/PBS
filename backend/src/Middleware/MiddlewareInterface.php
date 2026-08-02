<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\Request;
use App\Http\Response;

/**
 * Interfejs middleware — każdy middleware może przerwać łańcuch zwracając Response.
 */
interface MiddlewareInterface
{
    /**
     * @param callable(Request): Response $next
     */
    public function process(Request $request, callable $next): Response;
}