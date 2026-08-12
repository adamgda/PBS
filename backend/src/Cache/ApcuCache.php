<?php

declare(strict_types=1);

namespace App\Cache;

/**
 * Cache APCu — szybki cache w pamięci współdzielonej (produkcja bez Redis).
 *
 * Wymaga rozszerzenia `ext-apcu`. Gdy APCu jest niedostępne, klasa zachowuje
 * się jak no-op (graceful degradation — dokumentacja 14.5).
 */
final class ApcuCache implements CacheInterface
{
    private const PREFIX = 'pbs:';

    public function get(string $key): mixed
    {
        if (!function_exists('apcu_fetch')) {
            return null;
        }

        $value = apcu_fetch(self::PREFIX . $key);

        return $value === false ? null : $value;
    }

    public function set(string $key, mixed $value, int $ttl): void
    {
        if (!function_exists('apcu_store')) {
            return;
        }

        apcu_store(self::PREFIX . $key, $value, $ttl);
    }

    public function delete(string $key): void
    {
        if (!function_exists('apcu_delete')) {
            return;
        }

        apcu_delete(self::PREFIX . $key);
    }

    public function clear(): void
    {
        if (!function_exists('apcu_clear_cache')) {
            return;
        }

        apcu_clear_cache();
    }

    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $cached = $this->get($key);
        if ($cached !== null) {
            return $cached;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    public function invalidateTag(string $tag): void
    {
        // APCu nie ma natywnego tagowania — używamy klucza wersji tagu.
        // Klucze cache zawierają wersję tagu w nazwie (patrz CacheManager).
        if (!function_exists('apcu_inc')) {
            return;
        }

        apcu_inc(self::PREFIX . 'tag:' . $tag, 1, $success);
    }
}
