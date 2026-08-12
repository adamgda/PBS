<?php

declare(strict_types=1);

namespace App\Cache;

/**
 * Menedżer cache z invalidacją tagową (dokumentacja 14.2).
 *
 * Wzorzec: każdy tag ma licznik wersji przechowywany w cache pod kluczem
 * `tag:{tag}`. Klucz danych jest zapisywany pod nazwą zawierającą wersje
 * wszystkich powiązanych tagów. Unieważnienie taga (increment wersji) sprawia,
 * że kolejny odczyt nie trafia w stary klucz → dane są odświeżane.
 *
 * Przykład:
 *  - `remember('dashboard:summary', 60, ['dashboard'], fn() => ...)`
 *  - po mutacji: `invalidate(['dashboard'])` → następny odczyt przelicza KPI.
 */
final class CacheManager
{
    private const TAG_PREFIX = 'tag:';

    public function __construct(
        private readonly CacheInterface $cache,
    ) {}

    /**
     * Pobiera wartość z cache lub wylicza przez callback i zapisuje.
     *
     * @param array<int, string> $tags
     * @param callable(): mixed $callback
     */
    public function remember(string $key, int $ttl, array $tags, callable $callback): mixed
    {
        $versionedKey = $this->versionedKey($key, $tags);

        $cached = $this->cache->get($versionedKey);
        if ($cached !== null) {
            return $cached;
        }

        $value = $callback();
        $this->cache->set($versionedKey, $value, $ttl);

        // Zapamiętaj mapowanie klucz → wersjonowany klucz (do czyszczenia).
        $this->cache->set($this->pointerKey($key), $versionedKey, $ttl);

        return $value;
    }

    /**
     * Unieważnia wszystkie klucze powiązane z podanymi tagami.
     *
     * @param array<int, string> $tags
     */
    public function invalidate(array $tags): void
    {
        foreach ($tags as $tag) {
            $this->bumpTag($tag);
        }
    }

    /**
     * Usuwa konkretny klucz z cache (bez tagów).
     */
    public function forget(string $key): void
    {
        $pointer = $this->cache->get($this->pointerKey($key));
        if (is_string($pointer)) {
            $this->cache->delete($pointer);
        }
        $this->cache->delete($this->pointerKey($key));
    }

    public function clear(): void
    {
        $this->cache->clear();
    }

    /**
     * @param array<int, string> $tags
     */
    private function versionedKey(string $key, array $tags): string
    {
        $parts = [$key];
        foreach ($tags as $tag) {
            $parts[] = $tag . ':' . $this->tagVersion($tag);
        }

        return implode('|', $parts);
    }

    private function pointerKey(string $key): string
    {
        return 'ptr:' . $key;
    }

    private function tagVersion(string $tag): int
    {
        $version = $this->cache->get(self::TAG_PREFIX . $tag);

        return is_int($version) ? $version : 0;
    }

    private function bumpTag(string $tag): void
    {
        $version = $this->tagVersion($tag) + 1;
        $this->cache->set(self::TAG_PREFIX . $tag, $version, 0);
    }
}
