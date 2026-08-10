<?php

declare(strict_types=1);

namespace App\Services;

use App\Repository\AnalyticsRepository;

/**
 * Serwis analityki — sekcja Analityka (Etap 12).
 *
 * Operacje (read-only): główne statystyki (overview), statystyki terminali,
 * pracowników, sprzętu oraz relacje między zasobami. Każdy endpoint przyjmuje
 * opcjonalny zakres dat (`date_from`, `date_to` w formacie Y-m-d); domyślnie
 * ostatnie 30 dni.
 *
 * Bezpieczeństwo:
 * - Walidacja formatu dat (Y-m-d) — nieprawidłowe wartości odrzucane (422).
 * - Brak operacji mutujących — brak potrzeby audit logu.
 */
final class AnalyticsService
{
    private const int DEFAULT_RANGE_DAYS = 30;

    public function __construct(
        private readonly AnalyticsRepository $analyticsRepository,
    ) {}

    /**
     * Główne statystyki (KPI) w zakresie dat.
     *
     * @param array{date_from?: string, date_to?: string} $filters
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function overview(array $filters): array
    {
        $range = $this->resolveRange($filters);
        if ($range['ok'] === false) {
            return ['error' => $range['error'], 'code' => $range['code']];
        }

        $row = $this->analyticsRepository->overview($range['date_from'], $range['date_to']);

        return [
            'date_from' => $range['date_from'],
            'date_to' => $range['date_to'],
            'total_orders' => $this->toInt($row['total_orders'] ?? 0),
            'total_hours' => $this->toFloat($row['total_hours'] ?? 0),
            'total_wages' => $this->toFloat($row['total_wages'] ?? 0),
            'total_value' => $this->toFloat($row['total_value'] ?? 0),
            'total_incidents' => $this->toInt($row['total_incidents'] ?? 0),
            'incident_downtime_hours' => $this->toFloat($row['incident_downtime_hours'] ?? 0),
        ];
    }

    /**
     * Statystyki terminali w zakresie dat.
     *
     * @param array{date_from?: string, date_to?: string} $filters
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function terminals(array $filters): array
    {
        $range = $this->resolveRange($filters);
        if ($range['ok'] === false) {
            return ['error' => $range['error'], 'code' => $range['code']];
        }

        $rows = $this->analyticsRepository->terminals($range['date_from'], $range['date_to']);

        return [
            'date_from' => $range['date_from'],
            'date_to' => $range['date_to'],
            'data' => array_map(fn (array $row): array => [
                'terminal_id' => $this->toInt($row['terminal_id'] ?? 0),
                'nazwa' => $this->nullableString($row['nazwa'] ?? null),
                'order_count' => $this->toInt($row['order_count'] ?? 0),
                'total_hours' => $this->toFloat($row['total_hours'] ?? 0),
            ], $rows),
        ];
    }

    /**
     * Statystyki pracowników w zakresie dat.
     *
     * @param array{date_from?: string, date_to?: string} $filters
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function employees(array $filters): array
    {
        $range = $this->resolveRange($filters);
        if ($range['ok'] === false) {
            return ['error' => $range['error'], 'code' => $range['code']];
        }

        $rows = $this->analyticsRepository->employees($range['date_from'], $range['date_to']);

        return [
            'date_from' => $range['date_from'],
            'date_to' => $range['date_to'],
            'data' => array_map(fn (array $row): array => [
                'employee_id' => $this->toInt($row['employee_id'] ?? 0),
                'imie' => $this->nullableString($row['imie'] ?? null),
                'nazwisko' => $this->nullableString($row['nazwisko'] ?? null),
                'total_hours' => $this->toFloat($row['total_hours'] ?? 0),
                'total_wages' => $this->toFloat($row['total_wages'] ?? 0),
                'rola' => $this->nullableString($row['rola'] ?? null),
            ], $rows),
        ];
    }

    /**
     * Statystyki sprzętu w zakresie dat.
     *
     * @param array{date_from?: string, date_to?: string} $filters
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function equipment(array $filters): array
    {
        $range = $this->resolveRange($filters);
        if ($range['ok'] === false) {
            return ['error' => $range['error'], 'code' => $range['code']];
        }

        $rows = $this->analyticsRepository->equipment($range['date_from'], $range['date_to']);

        return [
            'date_from' => $range['date_from'],
            'date_to' => $range['date_to'],
            'data' => array_map(fn (array $row): array => [
                'equipment_id' => $this->toInt($row['equipment_id'] ?? 0),
                'nazwa' => $this->nullableString($row['nazwa'] ?? null),
                'kategoria' => $this->nullableString($row['kategoria'] ?? null),
                'assignment_count' => $this->toInt($row['assignment_count'] ?? 0),
            ], $rows),
        ];
    }

    /**
     * Relacje między zasobami (top pracownicy) w zakresie dat.
     *
     * @param array{date_from?: string, date_to?: string} $filters
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function relations(array $filters): array
    {
        $range = $this->resolveRange($filters);
        if ($range['ok'] === false) {
            return ['error' => $range['error'], 'code' => $range['code']];
        }

        $rows = $this->analyticsRepository->relations($range['date_from'], $range['date_to']);

        return [
            'date_from' => $range['date_from'],
            'date_to' => $range['date_to'],
            'data' => array_map(fn (array $row): array => [
                'employee_id' => $this->toInt($row['employee_id'] ?? 0),
                'imie' => $this->nullableString($row['imie'] ?? null),
                'nazwisko' => $this->nullableString($row['nazwisko'] ?? null),
                'assignment_count' => $this->toInt($row['assignment_count'] ?? 0),
                'terminal_nazwa' => $this->nullableString($row['terminal_nazwa'] ?? null),
                'equipment_nazwa' => $this->nullableString($row['equipment_nazwa'] ?? null),
                'total_hours' => $this->toFloat($row['total_hours'] ?? 0),
            ], $rows),
        ];
    }

    /**
     * Wyznacza zakres dat z filtrów (domyślnie ostatnie 30 dni).
     *
     * @param array{date_from?: string, date_to?: string} $filters
     * @return array{ok: true, date_from: string, date_to: string}|array{ok: false, error: string, code: int}
     */
    private function resolveRange(array $filters): array
    {
        $dateFrom = is_string($filters['date_from'] ?? null) ? trim($filters['date_from']) : '';
        $dateTo = is_string($filters['date_to'] ?? null) ? trim($filters['date_to']) : '';

        if ($dateFrom === '' && $dateTo === '') {
            $dateTo = date('Y-m-d');
            $dateFrom = date('Y-m-d', strtotime('-' . self::DEFAULT_RANGE_DAYS . ' days'));
        }

        if ($dateFrom !== '' && $this->isValidDate($dateFrom) === false) {
            return ['ok' => false, 'error' => 'Invalid date_from', 'code' => 422];
        }
        if ($dateTo !== '' && $this->isValidDate($dateTo) === false) {
            return ['ok' => false, 'error' => 'Invalid date_to', 'code' => 422];
        }

        if ($dateFrom === '') {
            $dateFrom = date('Y-m-d', strtotime('-' . self::DEFAULT_RANGE_DAYS . ' days'));
        }
        if ($dateTo === '') {
            $dateTo = date('Y-m-d');
        }

        return [
            'ok' => true,
            'date_from' => $dateFrom . ' 00:00:00',
            'date_to' => $dateTo . ' 23:59:59',
        ];
    }

    private function isValidDate(string $date): bool
    {
        $ts = strtotime($date);

        return $ts !== false && date('Y-m-d', $ts) === $date;
    }

    private function toInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return 0;
    }

    private function toFloat(mixed $value): float
    {
        if (is_float($value)) {
            return $value;
        }
        if (is_int($value)) {
            return (float) $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return 0.0;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_string($value)) {
            $trimmed = trim($value);

            return $trimmed === '' ? null : $trimmed;
        }

        return null;
    }
}

