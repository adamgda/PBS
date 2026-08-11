<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\AlertSettingsService;

/**
 * Kontroler konfiguracji alertów — sekcja Ustawienia → Alerty (Etap 14).
 *
 * Endpointy:
 * GET    /api/v1/settings/alert-configs
 * POST   /api/v1/settings/alert-configs
 * PUT    /api/v1/settings/alert-configs/{id}
 * DELETE /api/v1/settings/alert-configs/{id}
 *
 * Wymaga uprawnienia sekcji `ustawienia` (wymuszane przez PermissionMiddleware na trasie).
 */
final class AlertSettingsController extends Controller
{
    public function __construct(
        private readonly AlertSettingsService $alertSettingsService,
    ) {}

    /**
     * GET /api/v1/settings/alert-configs — lista konfiguracji.
     *
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params = []): Response
    {
        return $this->json($this->alertSettingsService->list(), 200);
    }

    /**
     * POST /api/v1/settings/alert-configs — utworzenie konfiguracji.
     *
     * @param array<string, string> $params
     */
    public function store(Request $request, array $params = []): Response
    {
        $result = $this->alertSettingsService->create($request->body(), $request);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 201);
    }

    /**
     * PUT /api/v1/settings/alert-configs/{id} — edycja konfiguracji.
     *
     * @param array<string, string> $params
     */
    public function update(Request $request, array $params = []): Response
    {
        $id = $this->toInt($params['id'] ?? 0, 0);
        $result = $this->alertSettingsService->update($id, $request->body(), $request);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 200);
    }

    /**
     * DELETE /api/v1/settings/alert-configs/{id} — usunięcie konfiguracji.
     *
     * @param array<string, string> $params
     */
    public function destroy(Request $request, array $params = []): Response
    {
        $id = $this->toInt($params['id'] ?? 0, 0);
        $result = $this->alertSettingsService->delete($id, $request);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 200);
    }

    /**
     * Bezpiecznie wyciąga błąd z wyniku serwisu (type-safe — PHPStan level 9).
     *
     * @param array<string, mixed> $result
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

    private function toInt(mixed $value, int $default): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }
}
