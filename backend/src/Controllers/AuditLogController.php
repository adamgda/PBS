<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\AuditLogService;

/**
 * Kontroler logów audytowych (Dashboard → Logi audytowe) — endpointy:
 * GET    /api/v1/audit-logs
 * DELETE /api/v1/audit-logs
 *
 * Dostęp wyłącznie dla super_admin (wymuszane przez guard superAdminGuard w routingu).
 */
final class AuditLogController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * GET /api/v1/audit-logs — lista logów z paginacją i filtrami.
     *
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params = []): Response
    {
        $query = $request->query();

        $filters = [
            'action' => is_string($query['action'] ?? null) ? $query['action'] : '',
            'user_email' => is_string($query['user_email'] ?? null) ? $query['user_email'] : '',
            'sort' => is_string($query['sort'] ?? null) ? $query['sort'] : 'id',
            'direction' => is_string($query['direction'] ?? null) ? $query['direction'] : 'desc',
        ];

        $page = $this->toInt($query['page'] ?? 1, 1);
        $perPage = $this->toInt($query['per_page'] ?? 25, 25);

        return $this->json($this->auditLogService->list($filters, $page, $perPage), 200);
    }

    /**
     * DELETE /api/v1/audit-logs — czyszczenie całego logu audytowego.
     *
     * @param array<string, string> $params
     */
    public function clear(Request $request, array $params = []): Response
    {
        $cleared = $this->auditLogService->clear();

        return $this->json(['success' => true, 'cleared' => $cleared], 200);
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
