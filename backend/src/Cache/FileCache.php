<?php

declare(strict_types=1);

namespace App\Cache;

use RuntimeException;

/**
 * Cache plikowy — zapis serializowanych wartości do katalogu storage/cache.
 *
 * Używany, gdy `CACHE_DRIVER=file` (fallback bez APCu/Redis). Przetrwa między
 * żądaniami HTTP. Tag-based invalidation przez indeks tagów w pliku.
 */
final class FileCache implements CacheInterface
{
    private readonly string $dir;

    public function __construct(string $dir)
    {
        $this->dir = rtrim($dir, '/');
        if (!is_dir($this->dir) && !mkdir($this->dir, 0775, true) && !is_dir($this->dir)) {
            throw new RuntimeException("Cannot create cache directory: {$this->dir}");
        }
    }

    public function get(string $key): mixed
    {
        $file = $this->fileFor($key);
        if (!is_file($file)) {
            return null;
        }

        $data = @file_get_contents($file);
        if ($data === false) {
            return null;
        }

        $entry = @unserialize($data, ['allowed_classes' => false]);
        if (!is_array($entry) || !array_key_exists('value', $entry) || !array_key_exists('expiresAt', $entry)) {
            return null;
        }

        if ($entry['expiresAt'] !== 0 && $entry['expiresAt'] < time()) {
            @unlink($file);
            return null;
        }

        return $entry['value'];
    }

    public function set(string $key, mixed $value, int $ttl): void
    {
        $entry = [
            'value' => $value,
            'expiresAt' => $ttl > 0 ? time() + $ttl : 0,
        ];

        $file = $this->fileFor($key);
        $tmp = $file . '.tmp';
        $payload = serialize($entry);

        if (@file_put_contents($tmp, $payload, LOCK_EX) === false) {
            return;
        }
        @rename($tmp, $file);
    }

    public function delete(string $key): void
    {
        $file = $this->fileFor($key);
        if (is_file($file)) {
            @unlink($file);
        }
    }

    public function clear(): void
    {
        $files = glob($this->dir . '/*.cache');
        if ($files === false) {
            return;
        }
        foreach ($files as $file) {
            @unlink($file);
        }
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
        $index = $this->tagIndex();
        $keys = $index[$tag] ?? [];
        foreach ($keys as $key) {
            $this->delete($key);
        }
        unset($index[$tag]);
        $this->writeTagIndex($index);
    }

    /**
     * Powiązuje klucz z tagiem (używane przez CacheManager przy zapisie).
     */
    public function tag(string $key, string $tag): void
    {
        $index = $this->tagIndex();
        $index[$tag][] = $key;
        $this->writeTagIndex($index);
    }

    private function fileFor(string $key): string
    {
        return $this->dir . '/' . hash('sha256', $key) . '.cache';
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function tagIndex(): array
    {
        $file = $this->dir . '/tags.index';
        if (!is_file($file)) {
            return [];
        }

        $data = @file_get_contents($file);
        if ($data === false) {
            return [];
        }

        $index = @unserialize($data, ['allowed_classes' => false]);

        return is_array($index) ? $index : [];
    }

    /**
     * @param array<string, array<int, string>> $index
     */
    private function writeTagIndex(array $index): void
    {
        $file = $this->dir . '/tags.index';
        $tmp = $file . '.tmp';
        if (@file_put_contents($tmp, serialize($index), LOCK_EX) !== false) {
            @rename($tmp, $file);
        }
    }
}
