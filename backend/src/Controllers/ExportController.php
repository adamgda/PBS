<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\ExportService;

/**
 * Kontroler sekcji „Eksport danych" — endpointy:
 * GET /api/v1/exports/{type}?from=&to=
 *
 * Zwraca plik CSV (text/csv) z nagłówkiem Content-Disposition. Typ eksportu
 * wybierany jest z białej listy w `ExportService`. Wymaga uprawnienia sekcji
 * `export_csv` (PermissionMiddleware na trasie).
 */
final class ExportController extends Controller
{
    public function __construct(
        private readonly ExportService $exportService,
    ) {}

    /**
     * GET /api/v1/exports/{type}?from=&to=
     *
     * @param array<string, string> $params
     */
    public function export(Request $request, array $params = []): Response
    {
        $type = is_string($params['type'] ?? null) ? $params['type'] : '';
        $query = $request->query();

        $from = is_string($query['from'] ?? null) ? $query['from'] : '';
        $to = is_string($query['to'] ?? null) ? $query['to'] : '';

        if ($from !== '' && strtotime($from) === false) {
            return $this->error(422, 'Invalid from date');
        }
        if ($to !== '' && strtotime($to) === false) {
            return $this->error(422, 'Invalid to date');
        }

        $result = $this->exportService->export($type, $from, $to);

        if (array_key_exists('error', $result)) {
            return $this->error($result['code'], $result['error']);
        }

        $filename = (string) $result['filename'];
        $timestamp = date('Y-m-d_His');

        return Response::raw(
            200,
            $result['content'],
            [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}_{$timestamp}.csv\"",
                'Cache-Control' => 'no-store',
            ],
        );
    }
}
