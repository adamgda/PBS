<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Abstrakcja odpowiedzi JSON API.
 */
final class Response
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly int $statusCode,
        private readonly array $data = [],
        private array $headers = [],
        private bool $compressed = false,
        private ?string $rawBody = null,
    ) {}

    /**
     * @param array<string, mixed>|null $data
     * @param array<string, string> $headers
     */
    public static function json(int $statusCode, ?array $data = null, array $headers = []): self
    {
        return new self($statusCode, $data ?? [], $headers);
    }

    /**
     * Odpowiedź z surowym ciałem (np. CSV) — bez JSON-owej serializacji.
     * Nagłówki (w tym Content-Type i Content-Disposition) przekazywane są jawnie.
     *
     * @param array<string, string> $headers
     */
    public static function raw(int $statusCode, string $body, array $headers = []): self
    {
        return new self($statusCode, [], $headers, false, $body);
    }

    public static function success(mixed $data = null, int $statusCode = 200): self
    {
        return new self($statusCode, ['data' => $data]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function created(array $data = []): self
    {
        return new self(201, $data);
    }

    public static function noContent(): self
    {
        return new self(204, []);
    }

    /**
     * @param array<string, mixed>|null $details
     */
    public static function error(int $statusCode, string $message, ?array $details = null): self
    {
        $data = ['error' => $message];
        if ($details !== null) {
            $data['details'] = $details;
        }

        return new self($statusCode, $data);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->data;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Surowe ciało odpowiedzi (np. CSV); null dla odpowiedzi JSON.
     */
    public function rawBody(): ?string
    {
        return $this->rawBody;
    }

    public function header(string $name, string $value): void
    {
        $this->headers[$name] = $value;
    }

    public function getHeader(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }


    /**
     * Włącza kompresję gzip odpowiedzi (dokumentacja 14.3).
     * Kompresja jest stosowana tylko dla odpowiedzi > 1 KB.
     */
    public function enableCompression(): void
    {
        $this->compressed = true;
    }

    public function isCompressed(): bool
    {
        return $this->compressed;
    }

    /**
     * Wysyła odpowiedź do klienta.
     */
    public function send(): void
    {
        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        if ($this->statusCode === 204) {
            return;
        }

        // Odpowiedź surowa (np. CSV) — bez serializacji JSON.
        if ($this->rawBody !== null) {
            echo $this->rawBody;
            return;
        }

        header('Content-Type: application/json; charset=utf-8');
        $body = json_encode($this->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            $body = '{}';
        }

        // Kompresja gzip dla odpowiedzi > 1 KB (dokumentacja 14.3).
        if ($this->compressed && strlen($body) > 1024 && function_exists('gzencode')) {
            $compressed = gzencode($body, 6);
            if ($compressed !== false) {
                header('Content-Encoding: gzip');
                header('Vary: Accept-Encoding');
                $body = $compressed;
            }
        }

        echo $body;
    }
}