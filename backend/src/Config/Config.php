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
     * Parser pliku .env.
     *
     * Obsługuje:
     *  - komentarze (linia zaczynająca się od `#`),
     *  - wartości proste: `KEY=value`,
     *  - wartości w apostrofach/cudzysłowach jednoliniowe: `KEY="value"`,
     *  - wartości wieloliniowe w cudzysłowach (np. klucze PEM RS256):
     *      JWT_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----
     *      ...
     *      -----END PRIVATE KEY-----"
     *
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

        $lines = preg_split('/\r\n|\r|\n/', $contents);
        if ($lines === false) {
            return [];
        }

        $values = [];
        $count = count($lines);
        $i = 0;

        while ($i < $count) {
            $line = trim($lines[$i]);
            $i++;

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $pos));
            if ($key === '') {
                continue;
            }

            $value = ltrim(substr($line, $pos + 1));

            // Wartość ujęta w cudzysłów/apostrof — może być wieloliniowa.
            if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
                $quote = $value[0];
                $rest = substr($value, 1);

                // Jednoliniowa wartość cytowana (zamykający znak na tej samej linii).
                if ($rest !== '' && str_ends_with($rest, $quote)) {
                    $values[$key] = substr($rest, 0, -1);
                    continue;
                }

                // Wieloliniowa wartość cytowana — zbieraj kolejne linie aż do znaku zamykającego.
                $buffer = $rest;
                $closed = false;
                while (!$closed && $i < $count) {
                    $next = $lines[$i];
                    $i++;
                    $rtrimmed = rtrim($next);
                    if ($rtrimmed !== '' && str_ends_with($rtrimmed, $quote)) {
                        $buffer .= "\n" . substr($rtrimmed, 0, -1);
                        $closed = true;
                    } else {
                        $buffer .= "\n" . $next;
                    }
                }
                $values[$key] = $buffer;
            } else {
                // Wartość prosta — ewentualne otaczające cytoly odcięte (wsteczna kompatybilność).
                $values[$key] = trim($value, "\"'");
            }
        }

        return $values;
    }
}