<?php

declare(strict_types=1);

namespace App\Cache;

use App\Config\Config;

/**
 * Fabryka cache — tworzy driver na podstawie `CACHE_DRIVER` w .env.
 *
 * Wartości: `apcu` (produkcja, wymaga ext-apcu), `file` (fallback),
 * `array` (dev/testy). Nieznana wartość → `array` (graceful degradation).
 */
final class CacheFactory
{
    public static function fromConfig(Config $config): CacheManager
    {
        $driver = strtolower($config->get('CACHE_DRIVER', 'array') ?? 'array');

        $cache = match ($driver) {
            'apcu' => new ApcuCache(),
            'file' => new FileCache(__DIR__ . '/../../storage/cache'),
            default => new ArrayCache(),
        };

        return new CacheManager($cache);
    }
}
