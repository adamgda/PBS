<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Pomocnik "sparse fieldsets" (dokumentacja 14.3).
 *
 * Obsługuje `?fields=id,nazwa,status` — redukcja transferu przez zwracanie
 * tylko wskazanych kolumn. Domyślnie zwraca wszystkie pola (brak filtra).
 */
final class SparseFields
{
    /**
     * @param array<string, mixed> $query
     * @return array<int, string>|null Lista pól lub null, gdy brak `fields`.
     */
    public static function fromQuery(array $query): ?array
    {
        $fields = $query['fields'] ?? null;
        if (!is_string($fields) || trim($fields) === '') {
            return null;
        }

        $list = array_values(array_filter(
            array_map('trim', explode(',', $fields)),
            static fn (string $f): bool => $f !== '',
        ));

        return $list === [] ? null : $list;
    }

    /**
     * Przycina rekord do wskazanych pól (jeśli podano).
     *
     * @param array<string, mixed> $row
     * @param array<int, string>|null $fields
     * @return array<string, mixed>
     */
    public static function apply(array $row, ?array $fields): array
    {
        if ($fields === null) {
            return $row;
        }

        $result = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $row)) {
                $result[$field] = $row[$field];
            }
        }

        return $result;
    }
}
