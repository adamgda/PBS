<?php

declare(strict_types=1);

namespace App\Cache;

/**
 * Interfejs cache backendu (Etap 15a — strategia cache, dokumentacja 14.2).
 *
 * Obsługuje cache z TTL oraz invalidację tagową (tag-based cache):
 *  - `remember()` — pobiera z cache lub wywołuje callback i zapisuje wynik.
 *  - `invalidateTag()` — unieważnia wszystkie klucze powiązane z tagiem
 *    (np. `employees:all`, `employee:{id}`) po mutacji danych.
 */
interface CacheInterface
{
    public function get(string $key): mixed;

    public function set(string $key, mixed $value, int $ttl): void;

    public function delete(string $key): void;

    public function clear(): void;

    /**
     * Pobiera wartość z cache lub wylicza ją przez callback i zapisuje.
     *
     * @param callable(): mixed $callback
     */
    public function remember(string $key, int $ttl, callable $callback): mixed;

    /**
     * Unieważnia wszystkie klucze powiązane z danym tagiem.
     */
    public function invalidateTag(string $tag): void;
}
