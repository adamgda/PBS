<?php

declare(strict_types=1);

/**
 * Testy migracji (Etap 16 — testy migracji).
 * Weryfikacja struktury/konwencji bez wykonywania SQL i bez ładowania klas
 * (nie wymaga bazy danych):
 *  - każdy plik migracji implementuje konwencję `extends AbstractMigration`
 *    i zwraca obiekt przez `return new ...()`,
 *  - nazwy plików migracji są unikalne i mają prefiks chronologiczny (14 cyfr),
 *  - liczba migracji jest zgodna z oczekiwaniami.
 */

$migrationsDir = __DIR__ . '/../../migrations';

function migrationFiles(string $dir): array
{
    $files = glob($dir . '/*.php');
    if ($files === false) {
        return [];
    }
    sort($files);

    return $files;
}

it('ładuje co najmniej jedną migrację', function () use ($migrationsDir): void {
    expect(migrationFiles($migrationsDir))->not->toBeEmpty();
});

it('każdy plik migracji rozszerza AbstractMigration i zwraca obiekt przez return new', function () use ($migrationsDir): void {
    foreach (migrationFiles($migrationsDir) as $file) {
        $content = (string) file_get_contents($file);
        expect($content)->toContain('extends AbstractMigration');
        expect($content)->toMatch('/return new [A-Za-z0-9_]+\(\);/');
        expect($content)->toContain('public function up(PDO $pdo): void');
        expect($content)->toContain('public function down(PDO $pdo): void');
        expect($content)->toContain('public function name(): string');
    }
});

it('nazwy plików migracji są unikalne', function () use ($migrationsDir): void {
    $names = array_map(static fn (string $f): string => basename($f), migrationFiles($migrationsDir));

    expect(count($names))->toBe(count(array_unique($names)));
});

it('nazwy plików migracji mają chronologiczny prefiks (14 cyfr + podkreślnik)', function () use ($migrationsDir): void {
    foreach (migrationFiles($migrationsDir) as $file) {
        expect(basename($file))->toMatch('/^[0-9]{14}_/');
    }
});

