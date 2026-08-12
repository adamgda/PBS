<?php

declare(strict_types=1);

namespace App\Cache;

/**
 * Cache w pamięci procesu (array) — do środowiska deweloperskiego i testów.
 *
 * Nie przetrwa między żądaniami HTTP (każde żądanie to nowy proces w PHP-FPM),
 * ale jest idealny do testów jednostkowych i trybu dev z wbudowanym serwerem.
 */
final class ArrayCache implements CacheInterface
{
    /** @var array<string, array{value: mixed, expiresAt: int}> */
    private array $store = [];

    /** @var array<string, array<int, string>> Mapa tag => lista kluczy */
    private array $tags = [];

    public function get(string $key): mixed
    {
        if (!isset($this->store[$key])) {
            return null;
        }

        $entry = $this->store[$key];
        if ($entry['expiresAt'] !== 0 && $entry['expiresAt'] < time()) {
            unset($this->store[$key]);
            return null;
        }

        return $entry['value'];
    }

    public function set(string $key, mixed $value, int $ttl): void
    {
        $this->store[$key] = [
            'value' => $value,
            'expiresAt' => $ttl > 0 ? time() + $ttl : 0,
        ];
    }

    public function delete(string $key): void
    {
        unset($this->store[$key]);
    }

    public function clear(): void
    {
        $this->store = [];
        $this->tags = [];
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
        $keys = $this->tags[$tag] ?? [];
        foreach ($keys as $key) {
            unset($this->store[$key]);
        }
        unset($this->tags[$tag]);
    }

    /**
     * Powiązuje klucz z tagiem (używane przez CacheManager przy zapisie).
     */
    public function tag(string $key, string $tag): void
    {
        $this->tags[$tag][] = $key;
    }
}
