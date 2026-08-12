<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Pomocnik paginacji (dokumentacja 14.3).
 *
 * Wymusza domyślną stronę 25 rekordów i maksymalnie 100 (paginacja obowiązkowa
 * dla wszystkich list — zabronione zwracanie całej tabeli).
 *
 * Czytane z query string: `page` (1-based) i `per_page` (domyślnie 25, max 100).
 */
final class Paginator
{
    public const DEFAULT_PER_PAGE = 25;
    public const MAX_PER_PAGE = 100;

    /**
     * @param array<string, mixed> $query
     * @return array{page: int, perPage: int, offset: int}
     */
    public static function fromQuery(array $query): array
    {
        $page = self::toPositiveInt($query['page'] ?? 1, 1);
        $perPage = self::toPositiveInt($query['per_page'] ?? self::DEFAULT_PER_PAGE, self::DEFAULT_PER_PAGE);
        $perPage = min($perPage, self::MAX_PER_PAGE);

        $offset = ($page - 1) * $perPage;

        return [
            'page' => $page,
            'perPage' => $perPage,
            'offset' => $offset,
        ];
    }

    private static function toPositiveInt(mixed $value, int $default): int
    {
        if (is_int($value)) {
            return max(1, $value);
        }
        if (is_string($value) && is_numeric($value)) {
            return max(1, (int) $value);
        }

        return $default;
    }
}
