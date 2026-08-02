<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;

/**
 * Kontroler health-check endpoint.
 */
final class HealthController extends Controller
{
    /**
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params = []): Response
    {
        return $this->json([
            'status' => 'ok',
            'service' => 'PBS Backend API',
            'timestamp' => date('c'),
        ]);
    }
}
