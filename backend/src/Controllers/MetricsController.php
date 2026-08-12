<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;

/**
 * Kontroler metryk (Etap 15a — monitorowanie, dokumentacja 14.8).
 *
 * Przyjmuje raporty Web Vitals z frontendu (best-effort) i loguje je
 * do stderr w formacie JSON — do agregacji przez Grafana/Loki.
 * Endpoint publiczny (bez danych osobowych), chroniony rate limitingiem.
 */
final class MetricsController extends Controller
{
    public function webVitals(Request $request): Response
    {
        $body = $request->body();

        $name = is_string($body['name'] ?? null) ? $body['name'] : 'unknown';
        $value = is_numeric($body['value'] ?? null) ? (float) $body['value'] : 0.0;
        $rating = is_string($body['rating'] ?? null) ? $body['rating'] : 'unknown';

        error_log('[PBS:webvitals] ' . json_encode([
            'name' => $name,
            'value' => $value,
            'rating' => $rating,
            'ts' => gmdate('c'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return Response::noContent();
    }
}
