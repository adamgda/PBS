<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\QrService;

/**
 * Kontroler publicznych kodów QR (Etap 20) — obsługa zgłoszeń z naklejki QR.
 *
 * Endpointy publiczne (bez AuthMiddleware), objęte osobnym rate limitingiem:
 * GET  /api/v1/qr/{token}           — info o maszynie (bez danych osobowych)
 * POST /api/v1/qr/{token}/incident  — anonimowe zgłoszenie awarii
 * POST /api/v1/qr/{token}/daily-report — anonimowy raport OC
 */
final class QrController extends Controller
{
    public function __construct(
        private readonly QrService $qrService,
    ) {}

    /**
     * GET /api/v1/qr/{token} — info o maszynie; 404 dla nieistniejącego tokena.
     *
     * @param array<string, string> $params
     */
    public function machine(Request $request, array $params = []): Response
    {
        $token = is_string($params['token'] ?? null) ? $params['token'] : '';
        $result = $this->qrService->machine($token);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 200);
    }

    /**
     * POST /api/v1/qr/{token}/incident — anonimowe zgłoszenie awarii.
     *
     * @param array<string, string> $params
     */
    public function createIncident(Request $request, array $params = []): Response
    {
        $token = is_string($params['token'] ?? null) ? $params['token'] : '';
        $result = $this->qrService->createIncident($token, $request->body());

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 201);
    }

    /**
     * POST /api/v1/qr/{token}/daily-report — anonimowy raport obsługi codziennej.
     *
     * @param array<string, string> $params
     */
    public function createDailyReport(Request $request, array $params = []): Response
    {
        $token = is_string($params['token'] ?? null) ? $params['token'] : '';
        $result = $this->qrService->createDailyReport($token, $request->body());

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 201);
    }

    // --- Pomocnicze ---

    /**
     * @param array<int|string, mixed> $result
     */
    private function errorResponse(array $result): ?Response
    {
        if (!array_key_exists('error', $result)) {
            return null;
        }

        $code = $result['code'] ?? 500;
        $message = $result['error'];

        if (is_int($code) && is_string($message)) {
            return $this->error($code, $message);
        }

        return $this->error(500, 'Unexpected error');
    }
}
