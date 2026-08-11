<?php

declare(strict_types=1);

namespace App\Config;

/**
 * Konfiguracja aplikacji wczytywana z pliku .env.
 */
final class Config
{
    /** @var array<string, string> */
    private array $values;

    /**
     * @param array<string, string> $values
     */
    public function __construct(array $values)
    {
        $this->values = $values;
    }

    public static function fromEnvFile(string $path): self
    {
        return new self(self::parseEnvFile($path));
    }

    public function get(string $key, ?string $default = null): ?string
    {
        return $this->values[$key] ?? $default;
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->values[$key] ?? null;
        if ($value === null || $value === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    /**
     * Uznajemy środowisko za produkcyjne, gdy domena API nie wskazuje na localhost.
     */
    public function isProduction(): bool
    {
        $apiBaseUrl = $this->get('API_BASE_URL', 'http://localhost:8080') ?? 'http://localhost:8080';

        return !str_contains($apiBaseUrl, 'localhost');
    }

    public function getRequired(string $key): string
    {
        $value = $this->values[$key] ?? null;
        if ($value === null) {
            throw new \RuntimeException("Missing required config key: {$key}");
        }

        return $value;
    }

    /**
     * @return array<string, string>
     */
    private static function parseEnvFile(string $path): array
    {
        if (!file_exists($path)) {
            return [];
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return [];
        }

        $values = [];
        $lines = preg_split('/\r\n|\r|\n/', $contents);
        if ($lines === false) {
            return [];
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));
            $value = trim($value, "\"'");

            if ($key !== '') {
                $values[$key] = $value;
            }
        }

        return $values;
    }
}