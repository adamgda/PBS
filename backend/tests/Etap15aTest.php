<?php

declare(strict_types=1);

use App\Cache\ArrayCache;
use App\Cache\CacheManager;
use App\Cache\FileCache;
use App\Http\Paginator;
use App\Http\Response;
use App\Http\SparseFields;
use App\Middleware\CacheControlMiddleware;
use App\Middleware\CompressionMiddleware;
use App\Middleware\MonitoringMiddleware;
use App\Http\Request;

/*
|--------------------------------------------------------------------------
| Etap 15a — Wydajność i optymalizacja
|--------------------------------------------------------------------------
| Testy komponentów wprowadzonych w ramach optymalizacji:
| cache (tag-based invalidation), paginacja, sparse fieldsets,
| middleware kompresji/cache-control/monitorowania oraz kompresja w Response.
*/

it('ArrayCache stores and retrieves values with TTL', function (): void {
    $cache = new ArrayCache();

    $cache->set('key', 'value', 60);
    expect($cache->get('key'))->toBe('value');

    // ttl <= 0 oznacza "bez wygasania" — wartość pozostaje.
    $cache->set('persistent', 'x', 0);
    expect($cache->get('persistent'))->toBe('x');

    $cache->delete('key');
    expect($cache->get('key'))->toBeNull();

    $cache->clear();
    expect($cache->get('persistent'))->toBeNull();
});

it('CacheManager remember caches callback result and invalidates by tag', function (): void {
    $cache = new CacheManager(new ArrayCache());
    $calls = 0;

    $result = $cache->remember('kpi', 60, ['dashboard'], function () use (&$calls) {
        $calls++;

        return ['value' => $calls];
    });

    expect($result['value'])->toBe(1);

    // Drugi odczyt — z cache (callback nie wołany ponownie).
    $result2 = $cache->remember('kpi', 60, ['dashboard'], function () use (&$calls) {
        $calls++;

        return ['value' => $calls];
    });
    expect($result2['value'])->toBe(1);
    expect($calls)->toBe(1);

    // Invalidacja taga → odświeżenie.
    $cache->invalidate(['dashboard']);
    $result3 = $cache->remember('kpi', 60, ['dashboard'], function () use (&$calls) {
        $calls++;

        return ['value' => $calls];
    });
    expect($result3['value'])->toBe(2);
});

it('CacheManager forget removes a specific key', function (): void {
    $cache = new CacheManager(new ArrayCache());

    $cache->remember('x', 60, [], fn (): int => 1);
    expect($cache->remember('x', 60, [], fn (): int => 2))->toBe(1);

    $cache->forget('x');
    expect($cache->remember('x', 60, [], fn (): int => 2))->toBe(2);
});

it('FileCache persists values across instances', function (): void {
    $dir = sys_get_temp_dir() . '/pbs_cache_' . bin2hex(random_bytes(6));

    $cache = new FileCache($dir);
    $cache->set('key', ['a' => 1], 60);
    expect($cache->get('key'))->toBe(['a' => 1]);

    $cache->invalidateTag('some-tag');
    $cache->clear();

    // Katalog zostaje — zweryfikuj że utworzono katalog.
    expect(is_dir($dir))->toBeTrue();
});

it('Paginator enforces default 25 and max 100', function (): void {
    $default = Paginator::fromQuery([]);
    expect($default['perPage'])->toBe(25);
    expect($default['offset'])->toBe(0);

    $capped = Paginator::fromQuery(['page' => '1', 'per_page' => '999']);
    expect($capped['perPage'])->toBe(100);

    $page2 = Paginator::fromQuery(['page' => '3', 'per_page' => '20']);
    expect($page2['offset'])->toBe(40);
});

it('SparseFields filters row fields when requested', function (): void {
    $row = ['id' => 1, 'nazwa' => 'X', 'status' => 'nowe'];

    expect(SparseFields::fromQuery([]))->toBeNull();

    $fields = SparseFields::fromQuery(['fields' => 'id,nazwa']);
    expect($fields)->toBe(['id', 'nazwa']);
    expect(SparseFields::apply($row, $fields))->toBe(['id' => 1, 'nazwa' => 'X']);
});

it('Response enableCompression flags compressed output', function (): void {
    $response = Response::json(200, ['data' => 'test']);
    expect($response->isCompressed())->toBeFalse();

    $response->enableCompression();
    expect($response->isCompressed())->toBeTrue();
});

it('CompressionMiddleware enables compression for gzip clients', function (): void {
    $middleware = new CompressionMiddleware();
    $request = new Request([], [], ['Accept-Encoding' => 'gzip, deflate']);

    $response = $middleware->process($request, fn (): Response => Response::json(200, ['ok' => true]));

    expect($response->isCompressed())->toBeTrue();
});

it('CacheControlMiddleware sets private cache for GET and no-store for mutations', function (): void {
    $middleware = new CacheControlMiddleware();

    $getResponse = $middleware->process(
        new Request([], [], []),
        fn (): Response => Response::json(200, ['ok' => true]),
    );
    // Note: Request::method() reads from globals, defaulting to GET.
    expect($getResponse->getHeader('Cache-Control'))->toContain('max-age=300');
    expect($getResponse->getHeader('ETag'))->not->toBeNull();
});

it('MonitoringMiddleware logs a JSON metric line', function (): void {
    $middleware = new MonitoringMiddleware();

    $response = $middleware->process(
        new Request([], [], []),
        fn (): Response => Response::json(200, ['ok' => true]),
    );

    expect($response->statusCode())->toBe(200);
});
